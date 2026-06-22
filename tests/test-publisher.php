<?php
/**
 * Publisher Tests
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
 * Class Test_Publisher
 */
class Test_Publisher extends WP_UnitTestCase {
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

	/**
	 * Test that when a service with multiple locations is published all locations' cnhsa_id postmeta fields are updated.
	 */
	public function test_service_with_multiple_locations_updates_all_location_cnhsa_id_postmeta_fields() {
		$service_post        = $this->factory->post->create_and_get(
			array(
				'post_type'   => 'services',
				'post_title'  => 'Test Service',
				'post_status' => 'publish',
			)
		);
		$location_post1      = $this->factory->post->create_and_get(
			array(
				'post_type'   => 'locations',
				'post_title'  => 'Test Location 1',
				'post_status' => 'publish',
			)
		);
		$location_post2      = $this->factory->post->create_and_get(
			array(
				'post_type'   => 'locations',
				'post_title'  => 'Test Location 2',
				'post_status' => 'publish',
			)
		);
		$cnhsa_service_id    = 900;
		$cnhsa_location_id_1 = 901;
		$cnhsa_location_id_2 = 902;
		$this->id_resolver->method( 'find_cnhsa_id' )->willReturnOnConsecutiveCalls( $cnhsa_location_id_1, $cnhsa_location_id_2, $cnhsa_service_id );
		$this->service_payload_factory->method( 'create_payload' )->willReturn(
			array(
				'title' => 'Test Service',
			)
		);
		$this->location_payload_factory->method( 'create_payload' )->willReturn(
			array(
				array(
					'cno_location_id' => $location_post1->ID,
					'title'           => 'Test Location 1',
				),
				array(
					'cno_location_id' => $location_post2->ID,
					'title'           => 'Test Location 2',
				),
			)
		);
		$this->gateway->method( 'publish_content' )->willReturnOnConsecutiveCalls(
			array(
				'data' => array(
					'id' => $cnhsa_location_id_1,
				),
			),
			array(
				'data' => array(
					'id' => $cnhsa_location_id_2,
				),
			),
			array(
				'data' => array(
					'id' => $cnhsa_service_id,
				),
			)
		);
		$this->publisher->update_services( $service_post );
		$this->assertEquals( $cnhsa_location_id_1, get_post_meta( $location_post1->ID, 'cnhsa_id', true ) );
		$this->assertEquals( $cnhsa_location_id_2, get_post_meta( $location_post2->ID, 'cnhsa_id', true ) );
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
				'data' => array(
					'id' => 123,
				),
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
				array(
					'title' => 'Test Location',
				),
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
				array(
					'title' => 'Test Location',
				),
			)
		);
		$this->gateway->expects( $this->once() )
		->method( 'publish_content' )
		->with( $this->url . '/location', array( 'title' => 'Test Location' ) )->willReturn(
			array(
				'data' => array(
					'id' => 456,
				),
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
			$this->stringContains( 'Publishing location post failed: Building location payload failed: Payload creation failed' )
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
			$this->stringContains( 'Publishing service post failed: Building service payload failed: Payload creation failed' )
		);
		$this->publisher->update_services( $service_post );
	}

	/**
	 * If the location payload factory returns null for a service, the
	 * published payload should omit `location_data`.
	 */
	public function test_service_omits_location_data_when_location_payload_null() {
		$service_post = $this->factory->post->create_and_get(
			array(
				'post_type'   => 'services',
				'post_title'  => 'Test Service',
				'post_status' => 'publish',
			)
		);
		$this->id_resolver->method( 'find_cnhsa_id' )->willReturn( 1 );
		$this->service_payload_factory->method( 'create_payload' )->willReturn(
			array(
				'title' => 'Test Service',
			)
		);
		$this->location_payload_factory->method( 'create_payload' )->willReturn( null );
		$this->gateway->expects( $this->once() )->method( 'publish_content' )->with(
			$this->anything(),
			$this->callback(
				function ( $payload ) {
					$this->assertIsArray( $payload );
					$this->assertArrayHasKey( 'title', $payload );
					$this->assertArrayNotHasKey( 'location_data', $payload );
					$this->assertEquals( 'Test Service', $payload['title'] );
					return true;
				}
			)
		);
		$this->publisher->update_services( $service_post );
	}

	/**
	 * Test that when a service with a single location is published the location's cnhsa_id postmeta field is updated.
	 */
	public function test_service_with_single_location_updates_location_cnhsa_id_postmeta_field() {
		$service_post      = $this->factory->post->create_and_get(
			array(
				'post_type'   => 'services',
				'post_title'  => 'Test Service',
				'post_status' => 'publish',
			)
		);
		$location_post     = $this->factory->post->create_and_get(
			array(
				'post_type'   => 'locations',
				'post_title'  => 'Test Location',
				'post_status' => 'publish',
			)
		);
		$cnhsa_service_id  = 789;
		$cnhsa_location_id = 456;
		$this->id_resolver->method( 'find_cnhsa_id' )->willReturn( $cnhsa_service_id );
		$this->service_payload_factory->method( 'create_payload' )->willReturn(
			array(
				'title' => 'Test Service',
			)
		);
		$this->location_payload_factory->method( 'create_payload' )->willReturn(
			array(
				array(
					'cno_location_id' => $location_post->ID,
					'title'           => 'Test Location',
				),
			)
		);
		$this->gateway->method( 'publish_content' )->willReturn(
			array(
				'data' => array(
					'id' => $cnhsa_location_id,
				),
			)
		);
		$this->publisher->update_services( $service_post );
		$this->assertEquals( $cnhsa_location_id, get_post_meta( $location_post->ID, 'cnhsa_id', true ) );
	}
}