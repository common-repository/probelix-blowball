<?php
/**
 * Probelix Blowball Integration
 *
 * @package   Blowball\Wordpress
 * @copyright Copyright (C) 2020-2023, Probelix GmbH - developer@probelix.de
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 *
 * @wordpress-plugin
 * Plugin Name: Probelix Blowball
 * Version:     1.3.10
 * Plugin URI:  https://www.blowball.io/wordpress
 * Description: Integrates Wordpress and Woocommerce with Probelix Blowball
 * Author:      Probelix GmbH
 * Author URI:  www.probelix.com
 * Text Domain: pbx-blowball
 * Domain Path: /languages/
 * License:     GPL v3
 *
 * WC requires at least: 3.0
 * WC tested up to: 3.5
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (! defined ( 'ABSPATH' )) {
	exit (); // Exit if accessed directly
}

require __DIR__ . '/vendor/autoload.php';

// Plugin Folder Path.
if (!defined( 'PBX_BLOWBALL_PLUGIN_DIR' ) )
	define( 'PBX_BLOWBALL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Plugin Root File.
if (!defined( 'PBX_BLOWBALL_PLUGIN_FILE' ) )
	define( 'PBX_BLOWBALL_PLUGIN_FILE', __FILE__ );

/**
 * This function should be used to access the Blowball singleton class
 * It's simpler to use this function instead of a global variable.
 *
 * @return PbxBlowball
 */
function PbxBlowball() {
	return \PbxBlowball\PbxBlowball::instance();
}

PbxBlowball();