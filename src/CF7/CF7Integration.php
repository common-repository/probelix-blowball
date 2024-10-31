<?php

namespace PbxBlowball\CF7;

use PbxBlowball\PbxBlowball;
use WPCF7_ContactForm;

if (! defined ( 'ABSPATH' )) {
	exit (); // Exit if accessed directly
}

/**
 * Main functions for Contact Form 7 Integration
 */
class CF7Integration {

    /**
     * @var PbxBlowball
     */
    private $core;

	public function __construct(PbxBlowball $pbxBlowball)
	{
        $this->core = $pbxBlowball;
        add_action('wpcf7_save_contact_form', [$this, 'saveCF7Config'], 9999, 1 );
        add_filter('wpcf7_editor_panels', [$this, 'registerEditorPanel'], 10, 1);
        add_action('delete_post', [$this, 'deleteConfig'], 10, 1);
        add_action('wpcf7_mail_sent', [$this, 'onCF7MailSent']);
        add_action('wpcf7_init', [$this,'registerFormTags']);
        add_action('wpcf7_swv_create_schema', [$this,'registerFormRules'], 10, 2);
    }


    function registerFormRules( $schema, $contact_form ) {
        $tags = $contact_form->scan_form_tags( array(
            'type' => array( 'pbx_store_select*' ),
        ) );

        foreach ( $tags as $tag ) {
            $schema->add_rule(
                wpcf7_swv_create_rule( 'required', array(
                    'field' => $tag->name,
                    'error' => wpcf7_get_message( 'invalid_required' ),
                ) )
            );
        }
    }

    function registerFormTags() {
        wpcf7_add_form_tag( ['pbx_store_select', 'pbx_store_select*'], [$this,'registerStoreSelect'],['name-attr' => true]);
    }

    function registerStoreSelect( $tag ) {
        if ( empty( $tag->name ) )
            return '';

        $validation_error = wpcf7_get_validation_error( $tag->name );

        $class = wpcf7_form_controls_class( $tag->type );

        if ( $validation_error ) {
            $class .= ' wpcf7-not-valid';
        }

        $atts = array();

        $atts['class'] = $tag->get_class_option( $class );
        $atts['id'] = $tag->get_id_option();
        $atts['tabindex'] = $tag->get_option( 'tabindex', 'signed_int', true );

        $atts['autocomplete'] = $tag->get_option(
            'autocomplete', '[-0-9a-zA-Z]+', true
        );

        if ( $tag->is_required() ) {
            $atts['aria-required'] = 'true';
        }

        if ( $validation_error ) {
            $atts['aria-invalid'] = 'true';
            $atts['aria-describedby'] = wpcf7_get_validation_error_reference(
                $tag->name
            );
        } else {
            $atts['aria-invalid'] = 'false';
        }

        $multiple = $tag->has_option( 'multiple' );
        $include_blank = $tag->has_option( 'include_blank' );
        $first_as_label = $tag->has_option( 'first_as_label' );

        if ( $tag->has_option( 'size' ) ) {
            $size = $tag->get_option( 'size', 'int', true );

            if ( $size ) {
                $atts['size'] = $size;
            } elseif ( $multiple ) {
                $atts['size'] = 4;
            } else {
                $atts['size'] = 1;
            }
        }

        $storeLabels = [];
        $storeValues = [];
		$storesPlugin = PbxBlowball()->getStorePlugin();
		if (!is_null($storesPlugin)){
			$stores = $storesPlugin->getActiveStores();
			foreach ($stores as $store){
                $storeLabels[] = $store->zip.' - '.$store->city.' - '.$store->address1;
                $storeValues[] = $store->store_id;
			}
		}


        $values = $storeValues;
        $labels = $storeLabels;

        $default_choice = $tag->get_default_option( null, array(
            'multiple' => $multiple,
        ) );

        if ( $include_blank
        or empty( $values ) ) {
            array_unshift(
                $labels,
                __( '&#8212;Please choose a store&#8212;', PbxBlowball::PLUGIN_NAME)
            );
            array_unshift( $values, '' );
        } elseif ( $first_as_label ) {
            $values[0] = '';
        }

        $html = '';
        $hangover = wpcf7_get_hangover( $tag->name );

        foreach ( $values as $key => $value ) {
            if ( $hangover ) {
                $selected = in_array( $value, (array) $hangover, true );
            } else {
                $selected = in_array( $value, (array) $default_choice, true );
            }

            $item_atts = array(
                'value' => $value,
                'selected' => $selected,
            );

            $label = isset( $labels[$key] ) ? $labels[$key] : $value;

            $html .= sprintf(
                '<option %1$s>%2$s</option>',
                wpcf7_format_atts( $item_atts ),
                esc_html( $label )
            );
        }

        $atts['multiple'] = (bool) $multiple;
        $atts['name'] = $tag->name . ( $multiple ? '[]' : '' );

        $html = sprintf(
            '<span class="wpcf7-form-control-wrap" data-name="%1$s"><select %2$s>%3$s</select>%4$s</span>',
            esc_attr( $tag->name ),
            wpcf7_format_atts( $atts ),
            $html,
            $validation_error
        );

        return $html;
    }

    /**
     * @param array<string, mixed> $panels
     * @return array<string, mixed>
     */
    public function registerEditorPanel($panels)
    {
        $panels['blowball-panel'] = [
            'title' => 'Blowball',
            'callback' => [$this, 'printEditorPanel']
        ];

        return $panels;
    }

    public function deleteConfig(int $postId):void
    {
        if(class_exists('WPCF7_ContactForm')){
            if (get_post_type($postId) == WPCF7_ContactForm::post_type) {
                CF7Helper::deleteConfig($postId);
            }
        }
    }

    /**
     * @param mixed $form (WPCF7_ContactForm)
     * @return void
     */
    public function onCf7MailSent($form)
    {
        $options = CF7Helper::getFormOptions($form->id);

        if (isset($options['active']) == false || $options['active'] == false) {
            return;
        }


        $handler = new CF7SubmissionHandler($this->core->getBlowballClient(), $this->core->getLogger(), $this->core->getNotifier());
        $handler->handleForm($form);
    }

    public function saveCF7Config():void{
        $currentForm = CF7Helper::getCurrentForm();
        if (is_null($currentForm->id))
            return;

            $options = isset( $_POST['wpcf7-blowball_options'] ) ? (array) $_POST['wpcf7-blowball_options'] : [];
        foreach ( $options as $key => &$value ) {
            $value = sanitize_text_field($value);
		}

        if (count($options) == 0)
            return;

        if (!isset($options['active']))
            return;

        if (empty($options['emailField'])) {
            $this->core->getNotifier()->error(__('Missing form configuration. Required: Email Field.', PbxBlowball::PLUGIN_NAME ));
            return;
        }

        CF7Helper::saveOptions($currentForm->id, $options);
    }

    /**
     * @param string $title
     * @param string $id
     * @param array<string, mixed> $options
     * @param string $type
     * @param array<mixed> $values
     * @param string|null $desc
     * @return void
     */
    public function printSectionHtml(string $title, string $id, $options, string $type, $values=[], ?string $desc=null){
        echo '<tr';
        if (!is_null($desc))
            echo ' class="hasNote"';
        echo '><th>'.esc_html($title).'</th><td>';
        if($type == 'checkbox'){
            echo '<input type="checkbox" name="wpcf7-blowball_options['.esc_html($id).']"';
            if (isset($options[$id]) && $options[$id] == true)
                echo 'checked';
            echo '>';
        } else if ($type == 'select'){
            echo '<select name="wpcf7-blowball_options['.esc_html($id).']"><option value=""></option>';
            if (is_array($values)){
                foreach ($values as $item){
                    echo '<option value="'.esc_attr($item->id).'"';
                    if (isset($options[$id]) && $item->id == $options[$id])
                        echo 'selected';
                    echo '>'.esc_html($item->name).'</option>';
                }
            }
            echo '</select>';
        } else if ($type == 'text'){
            echo '<input type="text" name="wpcf7-blowball_options['.esc_html($id).']"';
            if (isset($options[$id]))
                echo 'value="' . esc_attr($options[$id]) . '"';
            echo '>';
        }
        echo '</td></tr>';
        if (!is_null($desc)){
            echo '<tr><td colspan="2"><small>'.esc_html($desc).'</small></td></tr>';
        }
    }

    /**
     * @return void
     */
    public function printEditorPanel()
    {
        $currentForm = CF7Helper::getCurrentForm();
        if ((is_null($currentForm))||(is_null($currentForm->id))){
            $options = [];
            echo '<div class="pbxcf7"><h2>'.esc_html(__( 'Please save the form first', PbxBlowball::PLUGIN_NAME )).'</h2>';
            return;
        } else {
            $options = CF7Helper::getFormOptions($currentForm->id);
        }
        $fieldNames = CF7Helper::getFieldNames($currentForm);

        echo '<div class="pbxcf7"><h2>'.esc_html(__( 'Blowball Settings', PbxBlowball::PLUGIN_NAME )).'</h2><table class="mapping"><tbody>';
        $this->printSectionHtml(
            __( 'Sync active', PbxBlowball::PLUGIN_NAME ), 'active', $options, 'checkbox');
        $this->printSectionHtml(
            __( 'Email Field', PbxBlowball::PLUGIN_NAME ), 'emailField', $options, 'select', $fieldNames,
            __( 'Field that contains email address', PbxBlowball::PLUGIN_NAME ));
        $this->printSectionHtml(
            __( 'FirstName Field', PbxBlowball::PLUGIN_NAME ), 'fnameField', $options, 'select', $fieldNames,
            __( 'Field that contains the first name', PbxBlowball::PLUGIN_NAME ));
        $this->printSectionHtml(
            __( 'LastName Field', PbxBlowball::PLUGIN_NAME ), 'lnameField', $options, 'select', $fieldNames,
            __( 'Field that contains the last name', PbxBlowball::PLUGIN_NAME ));
        $this->printSectionHtml(
            __( 'Source', PbxBlowball::PLUGIN_NAME ), 'source', $options, 'text', [],
            __( 'Value for the source-field in blowball', PbxBlowball::PLUGIN_NAME ));
        $this->printSectionHtml(
            __( 'Store', PbxBlowball::PLUGIN_NAME ), 'storeField', $options, 'select', $fieldNames,
            __( 'Field that contains the store', PbxBlowball::PLUGIN_NAME ));
        $this->printSectionHtml(
            __( 'Condition Field', PbxBlowball::PLUGIN_NAME ), 'conditionField', $options, 'select', $fieldNames,
            __( 'Field that must be set to transfer', PbxBlowball::PLUGIN_NAME ));
        $this->printSectionHtml(
            __( 'Tags', PbxBlowball::PLUGIN_NAME ), 'tags', $options, 'text', [],
            __( 'Comma seperated list of tags for blowballs internal tag field', PbxBlowball::PLUGIN_NAME ));
        echo '</tbody></table></div>';
    }
}