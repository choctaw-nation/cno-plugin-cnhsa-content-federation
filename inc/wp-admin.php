<?php
/**
 * WP-Admin screen bootstrap file
 *
 * @package CNHSA_Federation
 */

use ChoctawNation\CNHSA_Federation\WP_Admin_Screen\Admin_Screen;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( is_admin() ) {
	foreach ( glob( __DIR__ . '/wp-admin-screen/class-*.php' ) as $file ) {
		require_once $file;
	}
	$screen = new Admin_Screen();
	add_action( 'admin_menu', array( $screen, 'register_menus' ) );
	add_action( 'admin_init', array( $screen, 'register_settings' ) );
}
