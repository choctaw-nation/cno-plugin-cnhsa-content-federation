<?php
/**
 * Plugin Name:       CNHSA Content Federation
 * Plugin URI:        https://github.com/choctawnation/cno-plugin-cnhsa-content-federation
 * Description:       Allows the CNO site to federate content to and from the CNHSA site.
 * Author:            Choctaw Nation of Oklahoma
 * Author URI:        https://www.choctawnation.com
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Tested up to:      6.9.1
 * Requires Plugins:  advanced-custom-fields-pro
 *
 * @package           CNHSA_Federation
 */

use ChoctawNation\CNHSA_Federation\Plugin_Loader;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}
require_once __DIR__ . '/vendor/autoload.php';

$cnhsa_federation_plugin = new Plugin_Loader();

// Plugin Lifecycle Hooks
register_activation_hook( __FILE__, array( $cnhsa_federation_plugin, 'activate' ) );

// Static method for uninstall since the plugin can't rely on instance methods.
register_uninstall_hook( __FILE__, array( 'ChoctawNation\CNHSA_Federation\Plugin_Loader', 'uninstall' ) );

// Load the Plugin
add_action( 'plugins_loaded', array( $cnhsa_federation_plugin, 'load_plugin' ) );
