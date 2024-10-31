<?php

namespace PbxBlowball\CF7;

use stdClass;
use WPCF7_ContactForm;

if (! defined ( 'ABSPATH' )) {
	exit (); // Exit if accessed directly
}

/**
 * Helper functions for Contact Form 7 Integration
 */
class CF7Helper
{
	/**
	 * @return mixed (WPCF7_ContactForm)
	 */
    public static function getCurrentForm()
    {
        if(class_exists('WPCF7_ContactForm'))
            return WPCF7_ContactForm::get_current();
    }

    /**
     * @param mixed $form (WPCF7_ContactForm)
     * @return array<mixed>
     */
    public static function getFieldNames($form)
    {
        $fields = [];
        $tags = $form->scan_form_tags();

        foreach ($tags as $tag) {
            if (!empty($tag['name'])) {
                $field = new stdClass();
                $field->id = $tag['name'];
                $field->name = $tag['name'];
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * @return array<mixed>
     */
    public static function getFormOptions(int $formId)
    {
        $data = get_post_meta($formId, '_wpcf7-blowball_options', true);
        return is_array($data) ? $data : [];
    }

    /**
     * @param int $formId
     * @param array<string, mixed> $options
     * @return int|bool
     */
    public static function saveOptions(int $formId, $options)
    {
        return update_post_meta($formId, '_wpcf7-blowball_options', $options);
    }

    /**
     * @return array<mixed>
     */
    public static function getAttributeMapping(int $formId)
    {
        $data = get_post_meta($formId, '_wpcf7-blowball_attribute_mapping', true);
        return is_array($data) ? $data : [];
    }

    /**
     * @param int $formId
     * @param array<int|string, mixed> $mapping
     * @return int|bool
     */
    public static function saveAttributeMapping(int $formId, $mapping)
    {
        return update_post_meta($formId, '_wpcf7-blowball_attribute_mapping', $mapping);
    }

    /**
     * @return array<mixed>
     */
    public static function getGlobalAttributeMapping(int $formId)
    {
        $data = get_post_meta($formId, '_wpcf7-blowball_global_attribute_mapping', true);
        return is_array($data) ? $data : [];
    }

    /**
     * @param int $formId
     * @param array<int|string, mixed> $mapping
     * @return int|bool
     */
    public static function saveGlobalAttributeMapping(int $formId, $mapping)
    {
        return update_post_meta($formId, '_wpcf7-blowball_global_attribute_mapping', $mapping);
    }

    public static function deleteConfig(int $formId):void
    {
        delete_post_meta($formId, '_wpcf7-blowball_options_mapping');
        delete_post_meta($formId, '_wpcf7-blowball_field_mapping');
        delete_post_meta($formId, '_wpcf7-blowball_option_mapping');
    }
}
