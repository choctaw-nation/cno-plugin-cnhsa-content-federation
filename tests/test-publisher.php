<?php
/**
 * Service Federation Tests
 *
 * @package ChoctawNation\CNHSA_Federation\Tests
 */

namespace ChoctawNation\CNHSA_Federation\Tests;

use ChoctawNation\CNHSA_Federation\Transport\Http\Service_Publisher;
use ChoctawNation\CNHSA_Federation\Transport\HTTP_Gateway;
use ChoctawNation\CNHSA_Federation\WP\ID_Resolver;
use ChoctawNation\CNHSA_Federation\WP\Notifier;
use ChoctawNation\CNHSA_Federation\WP\Payload\Location_Payload_Factory;
use ChoctawNation\CNHSA_Federation\WP\Payload\Service_Payload_Factory;
use WP_Error;
use WP_UnitTestCase;

/**
 * Class Test_Service_Federation
 */
class Test_Service_Federation extends WP_UnitTestCase {
	/**
	 * Mock ID resolver
	 *
	 * @var ID_Resolver $id_resolver
	 */
	private ID_Resolver $id_resolver;

	/**
	 * Mock notifier
	 *
	 * @var Notifier $notifier
	 */
	private Notifier $notifier;

	/**
	 * Mock service payload factory
	 *
	 * @var Service_Payload_Factory $service_payload_factory
	 */
	private Service_Payload_Factory $service_payload_factory;

	/**
	 * Mock location payload factory
	 *
	 * @var Location_Payload_Factory $location_payload_factory
	 */
	private Location_Payload_Factory $location_payload_factory;

	/**
	 * Gateway instance
	 *
	 * @var HTTP_Gateway $gateway
	 */
	private HTTP_Gateway $gateway;

	/**
	 * API URL for testing
	 *
	 * @var string $url
	 */
	private string $url = 'https://api.example.com/wp-json/cnhsa/v1';

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
	 * Set up test configs
	 */
	public function set_up(): void {
		parent::set_up();
		$this->id_resolver              = $this->createStub( ID_Resolver::class );
		$this->notifier                 = $this->getMockBuilder( Notifier::class )->getMock();
		$this->service_payload_factory  = $this->createStub( Service_Payload_Factory::class );
		$this->location_payload_factory = $this->createStub( Location_Payload_Factory::class );

		$this->gateway = new HTTP_Gateway(
			'local'
		);
	}

	/**
	 * Test that the service federation class can be instantiated and has the expected methods.
	 */
	public function test_insert_service_updates_post_meta_on_success() {
		$mock_service_post = self::factory()->post->create_and_get();

		HTTP_Requests::successful_request( array( 'data' => array( 'id' => 123 ) ), 201 );
		$this->service_payload_factory->method( 'create_payload' )->willReturn( array( 'title' => 'Test Service' ) );
		$this->gateway->publish_content( $this->url, array( 'title' => 'Test Service' ) );
		HTTP_Requests::clear_filters();

		$this->assertSame( 123, (int) get_post_meta( $mock_service_post->ID, 'cnhsa_services_id', true ) );
	}

	/**
	 * Test that the service federation class sends an email on failure.
	 */
	public function test_insert_service_sends_mail_on_failure() {
		HTTP_Requests::failed_request();
		$this->gateway->method( 'publish_content' )->will( $this->throwException( new \Exception( '500 error: Error occurred' ) ) );
		$this->gateway->publish_content( $this->url, array( 'title' => 'Test Service' ) );
		HTTP_Requests::clear_filters();
	}

	/**
	 * Test that the service federation class sends an email on payload error.
	 */
	public function test_build_payload_wp_error_sends_mail() {
		$mock_service_post = self::factory()->post->create_and_get();
		$this->service_payload_factory->method( 'create_payload' )->willReturn( new WP_Error( 'Payload error' ) );

		$this->gateway->publish_content( $this->url, array( 'title' => 'Test Service' ) );
	}

	public function test_service_with_location_payload_error_sends_mail() {
		$mock_service_post = self::factory()->post->create_and_get();
		$this->service_payload_factory->method( 'create_payload' )->willReturn( array( 'title' => 'Test Service' ) );
		$this->location_payload_factory->method( 'create_payload' )->willReturn( new WP_Error( 'Payload error' ) );
		$this->notifier->expects( $this->once() )->method( 'notify' )->with(
			'CNHSA Federation Payload Error',
			$this->stringContains( 'Error creating payload for post ID ' . $mock_service_post->ID )
		);
		$this->gateway->publish_content( $this->url, array( 'title' => 'Test Service' ) );
	}
}
