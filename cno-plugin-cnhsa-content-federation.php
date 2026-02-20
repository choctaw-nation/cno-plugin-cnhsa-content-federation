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

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

// autoload all files in the inc directory
foreach ( glob( __DIR__ . '/inc/*.php' ) as $file ) {
	require_once $file;
}

/**
 * Activation callback: initialize default options.
 */
function cnhsa_federation_activate() {
	$defaults = array(
		'username'     => '',
		'app_password' => '',
		'instructions' => "Create an application password in your profile and paste it here.\nVisit Users → Profile → Application Passwords.",
		'environments' => array(),
	);

	if ( false === get_option( 'cnhsa_federation_options', false ) ) {
		add_option( 'cnhsa_federation_options', $defaults );
	} else {
		$opts = get_option( 'cnhsa_federation_options', array() );
		$opts = wp_parse_args( $opts, $defaults );
		update_option( 'cnhsa_federation_options', $opts );
	}
	flush_rewrite_rules();
}

/**
 * Deactivation callback: currently no destructive actions.
 */
function cnhsa_federation_deactivate() {
	// Intentionally left blank. Use uninstall.php to remove data if desired.
}

register_activation_hook( __FILE__, 'cnhsa_federation_activate' );
register_deactivation_hook( __FILE__, 'cnhsa_federation_deactivate' );
