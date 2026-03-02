<?php
/**
 * Test Utils Class
 * Helper utils
 *
 * @package ChoctawNation\CNHSA_Federation\Tests
 */

namespace ChoctawNation\CNHSA_Federation\Tests;

/**
 * Class Test Utils
 */
class Test_Utils {
	/**
	 * Setup custom post types for testing.
	 */
	public static function setup_post_types() {
		$post_types = array( 'locations', 'services' );
		foreach ( $post_types as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				register_post_type( $post_type, array( 'public' => true ) );
			}
		}
	}

	/**
	 * Setup federation options for testing.
	 *
	 * @param array $overrides Optional overrides for default options.
	 */
	public static function setup_federation_options( array $overrides = array() ) {
		$base = array(
			'environments' => array( 'local' ),
			'credentials'  => array(
				'local' => array(
					'username'     => 'test-user',
					'app_password' => 'test-password',
				),
			),
		);

		update_option(
			'cnhsa_federation_options',
			array_merge(
				$base,
				$overrides
			)
		);
	}

	/**
	 * Teardown federation options after testing.
	 */
	public static function teardown_federation_options() {
		delete_option( 'cnhsa_federation_options' );
	}
}
