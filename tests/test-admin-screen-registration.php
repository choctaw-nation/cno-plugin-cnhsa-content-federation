<?php
/**
 * Admin Screen Registration Tests.
 * Tests check integration between Settings page and REST API, ensuring that options are sanitized and stored correctly, and that the transient for local URL is used as expected.
 *
 * @package ChoctawNation\CNHSA_Federation\Tests
 */

namespace ChoctawNation\CNHSA_Federation\Tests;

use ChoctawNation\CNHSA_Federation\WP\AdminScreen\Admin_Screen;
use WP_UnitTestCase;

/**
 * Class Test_Admin_Screen
 */
class Test_Admin_Screen extends WP_UnitTestCase {
	/**
	 * The option key for storing plugin settings in the database.
	 *
	 * @var string $option_key
	 */
	private const OPTION_KEY = 'cnhsa_federation_options';

	/**
	 * The transient key for storing the local URL in the database.
	 *
	 * @var string $transient_key
	 */
	private const TRANSIENT_KEY = 'cnhsa_federation_local_url';
	/**
	 * Clean up options and transients before each test to ensure a consistent environment.
	 */
	public function set_up(): void {
		parent::set_up();
		delete_transient( self::TRANSIENT_KEY );
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Clean up options and transients after each test to prevent side effects.
	 */
	public function tear_down(): void {
		delete_transient( self::TRANSIENT_KEY );
		delete_option( self::OPTION_KEY );
		parent::tear_down();
	}

	/**
	 * Test that the sanitize_options method correctly filters environments and credentials.
	 *
	 * @dataProvider data_sanitize_options_filters
	 *
	 * @param array  $input The input options array to sanitize.
	 * @param array  $expected_envs The expected array of valid environments after sanitization.
	 * @param string $expected_username The expected sanitized username.
	 * @param string $expected_app_password The expected sanitized app password.
	 * @param string $expected_local_url The expected sanitized local URL stored in the transient.
	 */
	public function test_sanitize_options_filters_environments_and_credentials( $input, $expected_envs, $expected_username, $expected_app_password, $expected_local_url ) {
		$screen = new Admin_Screen( self::OPTION_KEY, self::TRANSIENT_KEY );

		$out = $screen->sanitize_options( $input );

		$this->assertEquals( $expected_envs, $out['environments'] );
		$this->assertArrayHasKey( $expected_envs[0], $out['credentials'] );
		$this->assertEquals( $expected_username, $out['credentials'][ $expected_envs[0] ]['username'] );
		$this->assertEquals( $expected_app_password, $out['credentials'][ $expected_envs[0] ]['app_password'] );

		// localUrl should be stored in the transient
		$this->assertEquals( $expected_local_url, get_transient( self::TRANSIENT_KEY ) );
	}

	/**
	 * Sanitization data provider for testing environment filtering and credential sanitization.
	 */
	public function data_sanitize_options_filters() {
		return array(
			array(
				array(
					'environments' => array( 'production', 'invalid_env' ),
					'credentials'  => array(
						'production' => array(
							'username'     => '<b>user</b>',
							'app_password' => 'p&ass',
						),
					),
					'localUrl'     => 'https://example.com',
				),
				array( 'production' ),
				'user',
				'p&ass',
				'https://example.com',
			),
		);
	}

	/**
	 * Test that each selected environment may provide its own credentials.
	 *
	 * @dataProvider data_multiple_env_credentials
	 * @param array $input The input options array with per-environment credentials.
	 * @param array $expected_creds Associative array of expected credentials per environment.
	 */
	public function test_each_environment_can_have_different_credentials( $input, $expected_creds ) {
		$screen = new Admin_Screen( self::OPTION_KEY, self::TRANSIENT_KEY );

		$out = $screen->sanitize_options( $input );

		foreach ( $expected_creds as $env => $creds ) {
			$this->assertArrayHasKey( $env, $out['credentials'] );
			$this->assertEquals( $creds['username'], $out['credentials'][ $env ]['username'] );
			$this->assertEquals( $creds['app_password'], $out['credentials'][ $env ]['app_password'] );
		}
	}

	/**
	 * Data provider for testing that multiple environments can have different credentials.
	 *
	 * @return array Test cases with input options and expected credentials.
	 */
	public function data_multiple_env_credentials() {
		return array(
			array(
				array(
					'environments' => array( 'production', 'staging' ),
					'credentials'  => array(
						'production' => array(
							'username'     => '<b>alpha</b>',
							'app_password' => 'pass1',
						),
						'staging'    => array(
							'username'     => 'beta',
							'app_password' => 'pass2',
						),
					),
				),
				array(
					'production' => array(
						'username'     => 'alpha',
						'app_password' => 'pass1',
					),
					'staging'    => array(
						'username'     => 'beta',
						'app_password' => 'pass2',
					),
				),
			),
		);
	}

	/**
	 * Test that the field_local_cb method outputs the local URL from the transient if set.
	 */
	public function test_field_local_cb_prefers_transient() {
		$local = 'http://local.test';
		set_transient( self::TRANSIENT_KEY, $local, DAY_IN_SECONDS * 30 );
		$screen = new Admin_Screen( self::OPTION_KEY, self::TRANSIENT_KEY );

		ob_start();
		$screen->field_local_cb();
		$html = ob_get_clean();

		$this->assertStringContainsString( $local, $html );
	}

	/**
	 * Test that the field_credentials_cb method outputs saved credentials correctly.
	 *
	 * @dataProvider data_credentials_output
	 *
	 * @param array  $opts The options to save before rendering the field.
	 * @param string $expected_user The expected username to be found in the output HTML.
	 * @param string $expected_pass The expected app password to be found in the output HTML.
	 */
	public function test_field_credentials_cb_outputs_saved_credentials( $opts, $expected_user, $expected_pass ) {
		update_option( self::OPTION_KEY, $opts );

		$screen = new Admin_Screen( self::OPTION_KEY, self::TRANSIENT_KEY );

		ob_start();
		$screen->field_credentials_cb();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'value="' . $expected_user . '"', $html );
		$this->assertStringContainsString( 'value="' . $expected_pass . '"', $html );
	}

	/**
	 * Data provider for testing that saved credentials are output correctly in the field callback.
	 */
	public function data_credentials_output() {
		return array(
			array(
				array(
					'credentials' => array(
						'production' => array(
							'username'     => 'bob',
							'app_password' => 'pwd123',
						),
					),
				),
				'bob',
				'pwd123',
			),
		);
	}
}
