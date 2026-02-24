<?php
/**
 * Plugin Name:     CNHSA Content Federation
 * Plugin URI:      https://github.com/choctawnation/cno-plugin-cnhsa-content-federation
 * Description:     Allows the CNO site to federate content to and from the CNHSA site.
 * Author:          Choctaw Nation of Oklahoma
 * Author URI:      https://www.choctawnation.com
 * Version:         0.1.0
 *
 * @package         CNHSA_Federation
 */

use ChoctawNation\CNHSA_Federation\Plugin_Loader;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}
require_once __DIR__ . '/vendor/autoload.php';

$cnhsa_federation_plugin = new Plugin_Loader();

// Plugin Lifecycle Hooks
register_activation_hook( __FILE__, array( $cnhsa_federation_plugin, 'activate' ) );
register_deactivation_hook( __FILE__, array( $cnhsa_federation_plugin, 'deactivate' ) );
// Static method for uninstall since the plugin can't rely on instance methods.
register_uninstall_hook( __FILE__, array( 'ChoctawNation\CNHSA_Federation\Plugin_Loader', 'uninstall' ) );

// Load the Plugin
add_action( 'plugins_loaded', array( $cnhsa_federation_plugin, 'load_plugin' ) );
