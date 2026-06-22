<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Cno_Cnhsa_Content_Federation
 */

use ChoctawNation\CNHSA_Federation\Tests\Test_Utils;

$_tests_dir = getenv( 'WP_TESTS_DIR' );


if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( __DIR__, 1 ) . '/cno-plugin-cnhsa-content-federation.php';
	Test_Utils::setup_federation_options();
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );
tests_add_filter( 'muplugins_loaded', '_manually_load_acf' );

/**
 * Manually load the ACF plugin for testing.
 */
function _manually_load_acf() {
	global $_tests_dir;
	if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
		define( 'WP_PLUGIN_DIR', dirname( $_tests_dir, 1 ) . '/wordpress/wp-content/plugins' );
	}
	// Add ACF
	$acf_path = dirname( __DIR__ ) . '/vendor/advanced-custom-fields';

	// turn off the ACF admin UI to speed tests and reduce noise
	if ( ! defined( 'ACF_LITE' ) ) {
		define( 'ACF_LITE', true );
	}

	if ( file_exists( $acf_path . '/acf.php' ) ) {
		require_once $acf_path . '/acf.php';
	} elseif ( file_exists( WP_PLUGIN_DIR . '/advanced-custom-fields/acf.php' ) ) {
		require_once WP_PLUGIN_DIR . '/advanced-custom-fields/acf.php';
	} else {
		// Optional: throw or log so CI fails loudly
		fwrite( STDERR, "ACF plugin not found at {$acf_path}/acf.php or " . WP_PLUGIN_DIR . "/advanced-custom-fields/acf.php\n" );
	}
}

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";