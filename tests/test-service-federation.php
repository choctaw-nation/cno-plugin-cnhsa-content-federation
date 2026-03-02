<?php
/**
 * Service Federation Tests
 *
 * @package ChoctawNation\CNHSA_Federation\Tests
 */

namespace ChoctawNation\CNHSA_Federation\Tests;

use ChoctawNation\CNHSA_Federation\Transport\Http\Service_Publisher;
use ChoctawNation\CNHSA_Federation\WP\ID_Resolver;
use ChoctawNation\CNHSA_Federation\WP\Notifier;
use ChoctawNation\CNHSA_Federation\WP\Payload\Location_Payload_Factory;
use ChoctawNation\CNHSA_Federation\WP\Payload\Service_Payload_Factory;
use WP_UnitTestCase;

/**
 * Class Test_Service_Federation
 */
class Test_Service_Federation extends WP_UnitTestCase {
	/**
	 * Publisher instance
	 *
	 * @var Service_Publisher $publisher
	 */
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
	 * Publisher instance
	 *
	 * @var Service_Publisher $publisher
	 */
	private Service_Publisher $publisher;

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

		$this->publisher = new Service_Publisher(
			'local',
			$this->id_resolver,
			$this->service_payload_factory,
			$this->notifier,
			$this->location_payload_factory
		);
	}

	/**
	 * Test that the service federation class can be instantiated and has the expected methods.
	 */
	public function test_insert_service_updates_post_meta_on_success() {
		$mock_service_post = self::factory()->post->create_and_get();

		HTTP_Requests::successful_request( array( 'data' => array( 'id' => 123 ) ), 201 );
		$this->service_payload_factory->method( 'create_payload' )->willReturn( array( 'title' => 'Test Service' ) );
		$this->publisher->publish_content( $mock_service_post );
		HTTP_Requests::clear_filters();

		$this->assertSame( 123, (int) get_post_meta( $mock_service_post->ID, 'cnhsa_services_id', true ) );
	}

	/**
	 * Test that the service federation class sends an email on failure.
	 */
	public function test_insert_service_sends_mail_on_failure() {
		$mock_service_post = self::factory()->post->create_and_get();
		HTTP_Requests::failed_request();
		$this->service_payload_factory->method( 'create_payload' )->willReturn( array( 'title' => 'Test Service' ) );
		$this->notifier->expects( $this->once() )->method( 'notify' )->with(
			'CNHSA Federation API Error',
			$this->stringContains( '500' )
		);
		$this->publisher->publish_content( $mock_service_post );
		HTTP_Requests::clear_filters();
	}
}
