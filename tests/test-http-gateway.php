<?php
/**
 * HTTP Gateway Tests
 *
 * @package ChoctawNation\CNHSA_Federation\Tests
 */

namespace ChoctawNation\CNHSA_Federation\Tests;

use WP_UnitTestCase;
use ChoctawNation\CNHSA_Federation\Transport\HTTP_Gateway;

/**
 * Class Test_HTTP_Gateway
 */
class Test_HTTP_Gateway extends WP_UnitTestCase {
	/**
	 * Gateway instance
	 *
	 * @var HTTP_Gateway $gateway
	 */
	private HTTP_Gateway $gateway;

	/**
	 * Set up test environment
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		update_option(
			'cnhsa_federation_options',
			array(
				'environments' => array( 'local' ),
				'credentials'  => array(
					'local' => array(
						'username'     => 'test-user',
						'app_password' => 'test-password',
					),
				),
			)
		);
	}

	/**
	 * Clean up options after tests
	 */
	public static function tear_down_after_class(): void {
		delete_option( 'cnhsa_federation_options' );
		parent::tear_down_after_class();
	}

	/**
	 * Set up the test environment.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->gateway = new HTTP_Gateway( 'local' );
	}
	/**
	 * Test publish content
	 */
	public function test_base_url_is_set_correctly() {
		$this->assertStringContainsString( '.local', $this->gateway->base_url );
	}

	/**
	 * Test get_auth method throws exception when no environments are configured
	 */
	public function test_get_auth_throws_exception_when_no_environments_configured() {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'No target environments configured for federation.' );

		// Clear the options to simulate no environments configured .
		update_option( 'cnhsa_federation_options', array() );

		$reflection = new \ReflectionClass( HTTP_Gateway::class );
		$method     = $reflection->getMethod( 'get_auth' );
		$method->setAccessible( true );

		$method->invoke( $this->gateway );
	}

	/**
	 * Test get_auth method throws exception when credentials are missing
	 */
	public function test_get_auth_throws_exception_when_credentials_missing() {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( esc_html( "Credentials for environment 'local' are not fully configured." ) );

		// Set options with missing credentials.
		update_option(
			'cnhsa_federation_options',
			array(
				'environments' => array( 'local' ),
				'credentials'  => array(
					'local' => array(
						'username' => 'test-user',
						// 'app_password' is missing
					),
				),
			)
		);

		$reflection = new \ReflectionClass( HTTP_Gateway::class );
		$method     = $reflection->getMethod( 'get_auth' );
		$method->setAccessible( true );

		$method->invoke( $this->gateway );
	}

	/**
	 * Test publish content method returns data on successful request
	 */
	public function test_publish_content_returns_data() {
		HTTP_Requests::successful_request( array( 'status' => 'success' ), 201 );
		$response = $this->gateway->publish_content( 'https://api.example.com/', array( 'key' => 'value' ) );
		$this->assertEquals( array( 'status' => 'success' ), $response );
	}

	/**
	 * Test publish content method throws exception on failed request
	 */
	public function test_throws_exception_on_failed_request() {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( '500 error: Internal Server Error' );

		HTTP_Requests::failed_request( 'Internal Server Error', 500 );
		$this->gateway->publish_content( 'https://api.example.com/', array( 'key' => 'value' ) );
	}
}
