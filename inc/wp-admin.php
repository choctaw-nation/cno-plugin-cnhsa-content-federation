<?php
/**
 * WP-Admin screen bootstrap file
 *
 * @package CNHSA_Federation
 */

use ChoctawNation\CNHSA_Federation\WP_Admin_Screen\Rest_Router;
use ChoctawNation\CNHSA_Federation\WP_Admin_Screen\Admin_Screen;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// load admin class files
foreach ( glob( __DIR__ . '/wp-admin-screen/class-*.php' ) as $file ) {
	require_once $file;
}
// register rest routes immediately so they're available in the admin screen JS fetch calls
$router = new Rest_Router();
add_action( 'rest_api_init', array( $router, 'register_routes' ) );

if ( is_admin() ) {
	$screen = new Admin_Screen();
	add_action( 'admin_menu', array( $screen, 'register_menus' ) );
	add_action( 'admin_init', array( $screen, 'register_settings' ) );

	add_action(
		'admin_enqueue_scripts',
		function ( $hook_suffix ) {
			if ( 'cnhsa-federation_page_cnhsa-federation-settings' !== $hook_suffix ) {
				return;
			}
			$asset_file = require_once dirname( __DIR__, 1 ) . '/build/index.asset.php';
			wp_enqueue_script(
				'cnhsa-federation-admin',
				plugin_dir_url( __DIR__ ) . 'build/index.js',
				$asset_file['dependencies'],
				$asset_file['version'],
				array( 'strategy' => 'defer' )
			);
			wp_enqueue_style(
				'cnhsa-federation-admin',
				plugin_dir_url( __DIR__ ) . 'build/index.css',
				array(),
				$asset_file['version']
			);
		}
	);
}
