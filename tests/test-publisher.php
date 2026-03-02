<?php
/**
 * Service Federation Tests
 *
 * @package ChoctawNation\CNHSA_Federation\Tests
 */

namespace ChoctawNation\CNHSA_Federation\Tests;

use ChoctawNation\CNHSA_Federation\Transport\HTTP_Gateway;
use ChoctawNation\CNHSA_Federation\WP\ID_Resolver;
use ChoctawNation\CNHSA_Federation\WP\Notifier;
use ChoctawNation\CNHSA_Federation\WP\Payload\Location_Payload_Factory;
use ChoctawNation\CNHSA_Federation\WP\Payload\Service_Payload_Factory;
use ChoctawNation\CNHSA_Federation\WP\Publisher;
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
	 * Publisher instance
	 *
	 * @var Publisher $publisher
	 */
	private Publisher $publisher;

	/**
	 * API URL for testing
	 *
	 * @var string $url
	 */
	private string $url = 'https://cnhsa.local/wp-json/cnhsa/v1';

	/**
	 * Set up test environment
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Test_Utils::setup_federation_options();
		Test_Utils::setup_post_types();
	}

	/**
	 * Clean up options after tests
	 */
	public static function tear_down_after_class(): void {
		Test_Utils::teardown_federation_options();
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
		$this->gateway                  = $this->getMockBuilder( HTTP_Gateway::class )
		->setConstructorArgs( array( 'local' ) )
		->getMock();
		$this->publisher                = new Publisher( $this->id_resolver, $this->gateway, $this->service_payload_factory, $this->location_payload_factory, $this->notifier );
	}

	public function test_publisher_has_full_payload() {
		$service_post = $this->factory->post->create_and_get(
			array(
				'post_type'   => 'services',
				'post_title'  => 'Test Service',
				'post_status' => 'publish',
			)
		);
		$this->service_payload_factory->method( 'create_payload' )->willReturn(
			array(
				'title' => 'Test Service',
			)
		);
		$this->location_payload_factory->method( 'create_payload' )->willReturn(
			array(
				'address' => '123 Main St',
			)
		);
		$this->gateway->expects( $this->once() )->method( 'publish_content' )->with(
			$this->anything(),
			$this->callback(
				function ( $payload ) {
					$this->assertIsArray( $payload );
					$this->assertArrayHasKey( 'title', $payload );
					$this->assertArrayHasKey( 'location_data', $payload );
					$this->assertEquals( 'Test Service', $payload['title'] );
					$this->assertEquals( '123 Main St', $payload['location_data']['address'] );
					return true;
				}
			)
		);
		$this->publisher->update_services( $service_post );
	}

	/**
	 * Test service content posting to service endpoint
	 */
	public function test_service_content_posts_to_service_endpoint(): void {
		$service_post = $this->factory->post->create_and_get(
			array(
				'post_type'   => 'services',
				'post_title'  => 'Test Service',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $service_post->ID, 'cnhsa_id', '1' );
		$this->id_resolver->method( 'find_cnhsa_id' )->willReturn( 1 );
		$this->service_payload_factory->method( 'create_payload' )->willReturn(
			array(
				'title' => 'Test Service',
			)
		);
		$this->gateway->expects( $this->once() )
		->method( 'publish_content' )
		->with( $this->url . '/service/1', array( 'title' => 'Test Service' ) );
		$this->publisher->update_services( $service_post );
	}

	/**
	 * A new service will post to the service endpoint and update the post meta with the returned ID.
	 */
	public function test_service_content_posts_new_service(): void {
		$service_post = $this->factory->post->create_and_get(
			array(
				'post_type'   => 'services',
				'post_title'  => 'Test Service',
				'post_status' => 'publish',
			)
		);
		$this->id_resolver->method( 'find_cnhsa_id' )->willReturn( 0 );
		$this->service_payload_factory->method( 'create_payload' )->willReturn(
			array(
				'title' => 'Test Service',
			)
		);
		$this->gateway->expects( $this->once() )
		->method( 'publish_content' )
		->with( $this->url . '/service', array( 'title' => 'Test Service' ) )->willReturn(
			array(
				'id' => 123,
			)
		);
		$this->publisher->update_services( $service_post );
		$this->assertEquals( 123, get_post_meta( $service_post->ID, 'cnhsa_id', true ) );
	}

	/**
	 * A gateway exception will be handled and sent via Notifier.
	 */
	public function test_service_content_handles_gateway_exception(): void {
		$service_post = $this->factory->post->create_and_get(
			array(
				'post_type'   => 'services',
				'post_title'  => 'Test Service',
				'post_status' => 'publish',
			)
		);
		$this->id_resolver->method( 'find_cnhsa_id' )->willReturn( 0 );
		$this->service_payload_factory->method( 'create_payload' )->willReturn(
			array(
				'title' => 'Test Service',
			)
		);
		$this->gateway->method( 'publish_content' )
		->will( $this->throwException( new \Exception( 'Gateway error' ) ) );
		$this->notifier->expects( $this->once() )
		->method( 'notify' );
		$result = $this->publisher->update_services( $service_post );
	}

	/**
	 * Test location content posting to location endpoint
	 */
	public function test_location_content_posts_to_location_endpoint(): void {
		$location_post = $this->factory->post->create_and_get(
			array(
				'post_type'   => 'locations',
				'post_title'  => 'Test Location',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $location_post->ID, 'cnhsa_id', '1' );
		$this->id_resolver->method( 'find_cnhsa_id' )->willReturn( 1 );
		$this->location_payload_factory->method( 'create_payload' )->willReturn(
			array(
				'title' => 'Test Location',
			)
		);
		$this->gateway->expects( $this->once() )
		->method( 'publish_content' )
		->with( $this->url . '/location/1', array( 'title' => 'Test Location' ) );
		$this->publisher->update_locations( $location_post );
	}

	/**
	 * A new location will post to the location endpoint and update the post meta with the returned ID.
	 */
	public function test_location_content_posts_new_location(): void {
		$location_post = $this->factory->post->create_and_get(
			array(
				'post_type'   => 'locations',
				'post_title'  => 'Test Location',
				'post_status' => 'publish',
			)
		);
		$this->id_resolver->method( 'find_cnhsa_id' )->willReturn( 0 );
		$this->location_payload_factory->method( 'create_payload' )->willReturn(
			array(
				'title' => 'Test Location',
			)
		);
		$this->gateway->expects( $this->once() )
		->method( 'publish_content' )
		->with( $this->url . '/location', array( 'title' => 'Test Location' ) )->willReturn(
			array(
				'id' => 456,
			)
		);
		$this->publisher->update_locations( $location_post );
		$this->assertEquals( 456, get_post_meta( $location_post->ID, 'cnhsa_id', true ) );
	}

	/**
	 * A payload creation failure will be handled and sent via Notifier.
	 */
	public function test_email_sent_on_location_payload_failure() {
		$location_post = $this->factory->post->create_and_get(
			array(
				'post_type'   => 'locations',
				'post_title'  => 'Test Location',
				'post_status' => 'publish',
			)
		);
		$this->id_resolver->method( 'find_cnhsa_id' )->willReturn( 0 );
		$this->location_payload_factory->method( 'create_payload' )->willReturn(
			new WP_Error( 'payload_error', 'Payload creation failed' )
		);
		$this->notifier->expects( $this->once() )
		->method( 'notify' )
		->with(
			'CNHSA Locations Federation Failed',
			$this->stringContains( 'Publishing location post failed: Building payload failed: Payload creation failed' )
		);
		$this->publisher->update_locations( $location_post );
	}

	/**
	 * A payload creation failure will be handled and sent via Notifier.
	 */
	public function test_email_sent_on_service_payload_failure() {
		$service_post = $this->factory->post->create_and_get(
			array(
				'post_type'   => 'services',
				'post_title'  => 'Test Service',
				'post_status' => 'publish',
			)
		);
		$this->id_resolver->method( 'find_cnhsa_id' )->willReturn( 0 );
		$this->service_payload_factory->method( 'create_payload' )->willReturn(
			new WP_Error( 'payload_error', 'Payload creation failed' )
		);
		$this->notifier->expects( $this->once() )
		->method( 'notify' )
		->with(
			'CNHSA Services Federation Failed',
			$this->stringContains( 'Publishing service post failed: Building payload failed: Payload creation failed' )
		);
		$this->publisher->update_services( $service_post );
	}
}
