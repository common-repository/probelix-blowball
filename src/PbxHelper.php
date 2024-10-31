<?php
namespace PbxBlowball;

if (! defined ( 'ABSPATH' )) {
	exit (); // Exit if accessed directly
}

/**
 * General helper functions
 */
class PbxHelper
{
	/**
	 * @return array<string>
	 */
	public static function getPluginList(){
		$active_plugins = (array)get_option('active_plugins', []);
		if (is_multisite())
			$active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', []));
		return ($active_plugins);
	}

	public static function isWoocommerceActive():bool
	{
		$pluginName = 'woocommerce/woocommerce.php';
		$active = self::getPluginList();
		return in_array($pluginName, $active);
	}

	public static function isCF7Active():bool
	{
		$pluginName = 'contact-form-7/wp-contact-form-7.php';
		$active = self::getPluginList();
		return in_array($pluginName, $active);
	}

	public static function isAgileStoreFinderActive():bool
	{
		$pluginName = 'agile-store-locator/agile-store-locator.php';
		$active = self::getPluginList();
		return in_array($pluginName, $active);
	}

	/**
	 * Recursive array sanitation
	 *
	 * @param array<mixed> $array
	 * @return array<mixed>
	 */
	public static function sanitizeArray($array) {
		foreach ( $array as $key => &$value ) {
			if ( is_array($value) ) {
				$value = self::sanitizeArray($value);
			}
			else {
				$value = sanitize_text_field( $value );
			}
		}
		return $array;
	}

	public static function isWoocommercePre(string $version ):bool {
		if ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, $version, '<' ) ) {
			return true;
		} else {
			return false;
		}
	}


}
