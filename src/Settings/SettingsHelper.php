<?php

namespace PbxBlowball\Settings;

use PbxBlowball\PbxBlowball;

if (! defined ( 'ABSPATH' )) {
	exit (); // Exit if accessed directly
}

/**
 * Helper functions for Blowball Settings
 */
class SettingsHelper {

	/**
	 * @param  array<string,string> $field
	 * @return array<string,string>
	 */
	public static function getFieldDescription( $field ) {
		$description  = '';
		$tooltip_html = '';

		if (!empty( $field['desc_tip'] ) ) {
			$description  = $field['desc'];
			$tooltip_html = $field['desc_tip'];
		} elseif (!empty( $field['desc'] ) ) {
			$description = $field['desc'];
		}

		if ( $description && in_array( $field['type'], ['textarea', 'radio'], true ) ) {
			$description = '<p style="margin-top:0">' . wp_kses_post( $description ) . '</p>';
		} elseif ( $description && in_array( $field['type'], ['checkbox'], true ) ) {
			$description = wp_kses_post( $description );
		} elseif ( $description ) {
			$description = '<span class="description">' . wp_kses_post( $description ) . '</span>';
		}

		if ( $tooltip_html && in_array( $field['type'], ['checkbox'], true ) ) {
			$tooltip_html = '<p class="description">' . $tooltip_html . '</p>';
		} elseif ( $tooltip_html ) {
			$tooltip_html = '<span class="woocommerce-help-tip" data-tip="' . esc_attr($tooltip_html) . '"></span>';
		}

		return [
			'description'  => $description,
			'tooltip_html' => $tooltip_html,
		];
	}

	/**
	 * @param array[] $options
	 */
	public static function outputFields($options ):void {
		foreach ($options as $value) {
			if (!isset($value['type']))
				continue;
			if (!isset($value['id']))
				$value['id'] = '';
			if (!isset($value['title']))
				$value['title'] = isset($value['name']) ? $value['name'] : '';
			if (!isset($value['class']))
				$value['class'] = '';
			if (!isset( $value['css']))
				$value['css'] = '';
			if (!isset( $value['default']))
				$value['default'] = '';
			if (!isset( $value['desc']))
				$value['desc'] = '';
			if (!isset( $value['readonly']))
				$value['readonly'] = false;
			if (!isset( $value['enabled']))
				$value['enabled'] = true;
			if (!isset( $value['desc_tip']))
				$value['desc_tip'] = false;
			if (!isset( $value['placeholder']))
				$value['placeholder'] = '';
			if (!isset( $value['suffix']))
				$value['suffix'] = '';

			// Custom attribute handling.
			$customAttributes = [];
			if (!empty($value['custom_attributes']) && is_array($value['custom_attributes'])) {
				foreach ($value['custom_attributes'] as $attribute => $attribute_value) {
					$customAttributes[] = esc_attr($attribute).'="'.esc_attr($attribute_value).'"';
				}
			}

			// Description handling.
			$field_description 	= self::getFieldDescription($value);
			$description       	= $field_description['description'];
			$tooltip_html      	= $field_description['tooltip_html'];
			$option_value		= '';
			if ((array_key_exists('value',$value)&&(!is_null($value['value']))))
				$option_value = $value['value'];

			// Switch based on type.
			switch ( $value['type'] ) {

				// Section Titles.
				case 'title':
					if ( ! empty( $value['title'] ) ) {
						echo '<h2>' . esc_html( $value['title'] ) . '</h2>';
					}
					if ( ! empty( $value['desc'] ) ) {
						echo '<div id="' . esc_attr( sanitize_title( $value['id'] ) ) . '-description">';
						echo wp_kses_post( wpautop( wptexturize( $value['desc'] ) ) );
						echo '</div>';
					}
					echo '<table class="form-table">';
					break;

				// Section Ends.
				case 'sectionend':
					if ( ! empty( $value['id'] ) ) {
						do_action( 'woocommerce_settings_' . sanitize_title( $value['id'] ) . '_end' );
					}
					echo '</table>';
					if ( ! empty( $value['id'] ) ) {
						do_action( 'woocommerce_settings_' . sanitize_title( $value['id'] ) . '_after' );
					}
					break;

				// Standard text inputs and subtypes like 'number'.
				case 'text':
				case 'password':
				case 'datetime':
				case 'datetime-local':
				case 'date':
				case 'month':
				case 'time':
				case 'week':
				case 'number':
				case 'email':
				case 'url':
				case 'tel':
					?><tr valign="top">
						<th scope="row" class="titledesc">
							<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?> <?php echo esc_html($tooltip_html); ?></label>
						</th>
						<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
							<input
								name="<?php echo esc_attr( $value['id'] ); ?>"
								id="<?php echo esc_attr( $value['id'] ); ?>"
								type="<?php echo esc_attr( $value['type'] ); ?>"
								style="<?php echo esc_attr( $value['css'] ); ?>"
								value="<?php echo esc_attr( $option_value ); ?>"
								class="<?php echo esc_attr( $value['class'] ); ?>"
								<?php if ($value['readonly']==true) echo ' readonly ';?>
								placeholder="<?php echo esc_attr( $value['placeholder'] ); ?>"
								<?php echo esc_attr(implode( ' ', $customAttributes )); ?>
								/><?php echo esc_html( $value['suffix'] ); ?> <?php echo esc_html($description); ?>
						</td>
					</tr>
					<?php
					break;

				// Color picker.
				case 'color':
					?>
					<tr valign="top">
						<th scope="row" class="titledesc">
							<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?> <?php echo esc_html($tooltip_html); ?></label>
						</th>
						<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">&lrm;
							<span class="colorpickpreview" style="background: <?php echo esc_attr( $option_value ); ?>">&nbsp;</span>
							<input
								name="<?php echo esc_attr( $value['id'] ); ?>"
								id="<?php echo esc_attr( $value['id'] ); ?>"
								type="text"
								dir="ltr"
								style="<?php echo esc_attr( $value['css'] ); ?>"
								value="<?php echo esc_attr( $option_value ); ?>"
								class="<?php echo esc_attr( $value['class'] ); ?>colorpick"
								placeholder="<?php echo esc_attr( $value['placeholder'] ); ?>"
								<?php echo esc_attr(implode( ' ', $customAttributes )); ?>
								/>&lrm; <?php echo esc_html($description); ?>
								<div id="colorPickerDiv_<?php echo esc_attr( $value['id'] ); ?>" class="colorpickdiv" style="z-index: 100;background:#eee;border:1px solid #ccc;position:absolute;display:none;"></div>
						</td>
					</tr>
					<?php
					break;

				// Textarea.
				case 'textarea':
					?>
					<tr valign="top">
						<th scope="row" class="titledesc">
							<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?> <?php echo esc_html($tooltip_html); ?></label>
						</th>
						<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
							<?php echo esc_html($description); ?>

							<textarea
								name="<?php echo esc_attr( $value['id'] ); ?>"
								id="<?php echo esc_attr( $value['id'] ); ?>"
								style="<?php echo esc_attr( $value['css'] ); ?>"
								class="<?php echo esc_attr( $value['class'] ); ?>"
								placeholder="<?php echo esc_attr( $value['placeholder'] ); ?>"
								<?php echo esc_html(implode( ' ', $customAttributes )); ?>
								><?php echo esc_textarea( $option_value ); ?></textarea>
						</td>
					</tr>
					<?php
					break;

				// Select boxes.
				case 'select':
				case 'multiselect':
					?>
					<tr valign="top">
						<th scope="row" class="titledesc">
							<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?> <?php echo esc_html($tooltip_html); ?></label>
						</th>
						<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
							<select
								name="<?php echo esc_attr( $value['id'] ); ?><?php echo ( 'multiselect' === $value['type'] ) ? '[]' : ''; ?>"
								id="<?php echo esc_attr( $value['id'] ); ?>"
								style="<?php echo esc_attr( $value['css'] ); ?>"
								class="<?php echo esc_attr( $value['class'] ); ?>"
								<?php echo esc_html(implode( ' ', $customAttributes )); ?>
								<?php echo 'multiselect' === $value['type'] ? 'multiple="multiple"' : ''; ?>
								>
								<?php
								foreach ( $value['options'] as $key => $val ) {
									?>
									<option value="<?php echo esc_attr( $key ); ?>"
										<?php

										if ( is_array( $option_value ) ) {
											selected( in_array( (string) $key, $option_value, true ), true );
										} else {
											selected( $option_value, (string) $key );
										}

									?>
									>
									<?php echo esc_html( $val ); ?></option>
									<?php
								}
								?>
							</select> <?php echo esc_html($description); ?>
						</td>
					</tr>
					<?php
					break;

				// Radio inputs.
				case 'radio':
					?>
					<tr valign="top">
						<th scope="row" class="titledesc">
							<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?> <?php echo esc_html($tooltip_html); ?></label>
						</th>
						<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
							<fieldset>
								<?php echo esc_html($description); ?>
								<ul>
								<?php
								foreach ( $value['options'] as $key => $val ) {
									?>
									<li>
										<label><input
											name="<?php echo esc_attr( $value['id'] ); ?>"
											value="<?php echo esc_attr( $key ); ?>"
											type="radio"
											style="<?php echo esc_attr( $value['css'] ); ?>"
											class="<?php echo esc_attr( $value['class'] ); ?>"
											<?php echo esc_html(implode( ' ', $customAttributes )); ?>
											<?php checked( $key, $option_value ); ?>
											/> <?php echo esc_html( $val ); ?></label>
									</li>
									<?php
								}
								?>
								</ul>
							</fieldset>
						</td>
					</tr>
					<?php
					break;

				// Checkbox input.
				case 'checkbox':
					$visibility_class = [];

					if ( ! isset( $value['hide_if_checked'] ) ) {
						$value['hide_if_checked'] = false;
					}
					if ( ! isset( $value['show_if_checked'] ) ) {
						$value['show_if_checked'] = false;
					}
					if ( 'yes' === $value['hide_if_checked'] || 'yes' === $value['show_if_checked'] ) {
						$visibility_class[] = 'hidden_option';
					}
					if ( 'option' === $value['hide_if_checked'] ) {
						$visibility_class[] = 'hide_options_if_checked';
					}
					if ( 'option' === $value['show_if_checked'] ) {
						$visibility_class[] = 'show_options_if_checked';
					}

					if ( ! isset( $value['checkboxgroup'] ) || 'start' === $value['checkboxgroup'] ) {
						?>
							<tr valign="top" class="<?php echo esc_attr( implode( ' ', $visibility_class ) ); ?>">
								<th scope="row" class="titledesc"><?php echo esc_html( $value['title'] ); ?></th>
								<td class="forminp forminp-checkbox">
									<fieldset>
						<?php
					} else {
						?>
							<fieldset class="<?php echo esc_attr( implode( ' ', $visibility_class ) ); ?>">
						<?php
					}

					if ( ! empty( $value['title'] ) ) {
						?>
							<legend class="screen-reader-text"><span><?php echo esc_html( $value['title'] ); ?></span></legend>
						<?php
					}

					?>
						<label for="<?php echo esc_attr( $value['id'] ); ?>">
							<input
								name="<?php echo esc_attr( $value['id'] ); ?>"
								id="<?php echo esc_attr( $value['id'] ); ?>"
								type="checkbox"
								class="<?php echo esc_attr( isset( $value['class'] ) ? $value['class'] : '' ); ?>"
								value="1"
								<?php checked( $option_value, 'yes' ); ?>
								<?php echo esc_html(implode( ' ', $customAttributes )); ?>
							/> <?php echo esc_html($description); ?>
						</label> <?php echo esc_html($tooltip_html); ?>
					<?php

					if ( ! isset( $value['checkboxgroup'] ) || 'end' === $value['checkboxgroup'] ) {
									?>
									</fieldset>
								</td>
							</tr>
						<?php
					} else {
						?>
							</fieldset>
						<?php
					}
					break;

				case 'action':
					if ($value['enabled']==false)
						$disabled = 'disabled';
					else
						$disabled = '';
					echo '<input type="submit" '.$disabled.' name="submit_'.esc_html($value['action']).'" class="save-options" value="'. esc_html($value['label']) .'" />';
					break;

				// Single page selects.
				case 'single_select_page':
					$args = [
						'name'             => $value['id'],
						'id'               => $value['id'],
						'sort_column'      => 'menu_order',
						'sort_order'       => 'ASC',
						'show_option_none' => ' ',
						'class'            => $value['class'],
						'echo'             => false,
						'selected'         => absint($option_value),
						'post_status'      => 'publish,private,draft',
					];

					if ( isset( $value['args'] ) ) {
						$args = wp_parse_args( $value['args'], $args );
					}

					?>
					<tr valign="top" class="single_select_page">
						<th scope="row" class="titledesc">
							<label><?php echo esc_html($value['title'] ); ?> <?php echo esc_html($tooltip_html); ?></label>
						</th>
						<td class="forminp">
							<?php
							$placeholder = __( 'Select a page&hellip;', PbxBlowball::PLUGIN_NAME );
							$replacement = " data-placeholder='".esc_html($placeholder). "' style='" . esc_attr($value['css']) . "' class='" . esc_attr($value['class']). "' id=";
							echo str_replace(' id=', $replacement, wp_dropdown_pages( $args ) );
							echo esc_html($description);
							?>
						</td>
					</tr>
					<?php
					break;

				default:
					break;
			}
		}
	}
}