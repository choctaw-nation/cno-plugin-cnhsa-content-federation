<?php
/**
 * Location Payload Factory Tests
 *
 * @package ChoctawNation\CNHSA_Federation\Tests
 */

namespace ChoctawNation\CNHSA_Federation\Tests;

use ChoctawNation\CNHSA_Federation\WP\Payload\Location_Payload_Factory;
use WP_UnitTestCase;

/**
 * Class Test Location Payload Factory
 *
 * @package ChoctawNation\CNHSA_Federation\Tests
 */
class Test_Location_Payload_Factory extends WP_UnitTestCase {
	/**
	 * The payload factory instance.
	 *
	 * @var Location_Payload_Factory $payload_factory
	 */
	private Location_Payload_Factory $payload_factory;

	/**
	 * Set up custom post types and ACF fields before running tests.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Test_Utils::setup_post_types();
		ACF_Fields::register_fields( 'locations' );
	}

	/**
	 * Set up the test environment.
	 */
	public function set_up() {
		parent::set_up();
		$this->payload_factory = new Location_Payload_Factory();
	}

	/**
	 * Test that create_payload returns null for non-location post types.
	 */
	public function test_create_payload_for_non_location_post_type_returns_null() {
		$post = $this->factory->post->create_and_get(
			array(
				'post_type'  => 'page',
				'post_title' => 'Test Page',
			)
		);

		$payload = $this->payload_factory->create_payload( $post );
		$this->assertNull( $payload, 'Expected null payload for non-location post type.' );
	}

	/**
	 * Test that create_payload returns payload array for a single location post.
	 */
	public function test_create_payload_for_single_location_post_returns_payload_array() {
		$location = $this->factory->post->create_and_get(
			array(
				'post_type'  => 'locations',
				'post_title' => 'Test Location',
			)
		);
		$image    = $this->factory->attachment->create_upload_object( __DIR__ . '/image-placeholder.jpg' );
		// Set ACF fields for the location.
		update_field( 'photo', $image, $location->ID );
		update_field( 'address', '123 Main St', $location->ID );
		update_field( 'city_state_zip', 'Testville, TS 12345', $location->ID );
		update_field( 'phone_number', '555-0101', $location->ID );
		update_field( 'additional_phone_number', '', $location->ID );
		update_field( 'fax_number', '', $location->ID );

		$payload = $this->payload_factory->create_payload( $location );

		$this->assertIsArray( $payload );
		$this->assertCount( 1, $payload );

		$entry = $payload[0];
		$this->assertArrayHasKey( 'location_type', $entry );
		$this->assertArrayHasKey( 'cno_location_id', $entry );
		$this->assertEquals( $location->ID, $entry['cno_location_id'] );
		$this->assertEquals( '123 Main St', $entry['address'] );
		$this->assertEquals( 'Testville, TS 12345', $entry['city_state_zip'] );
		$this->assertEquals( '555-0101', $entry['phone_number'] );
		$this->assertArrayHasKey( 'location_name', $entry );
		$this->assertArrayHasKey( 'featured_image', $entry );
		$this->assertIsArray( $entry['featured_image'] );
		$this->assertArrayHasKey( 'src', $entry['featured_image'] );
		$this->assertArrayHasKey( 'cno_image_id', $entry['featured_image'] );
		$this->assertEquals( 'Test Location', $entry['location_name'] );
	}

	/**
	 * Test that a Choctaw location with a non-Health Facility type is set to 'external'.
	 */
	public function test_choctaw_location_non_health_facility_sets_location_type_external() {
		$location = $this->factory->post->create_and_get(
			array(
				'post_type'  => 'locations',
				'post_title' => 'Choctaw Non-Health Location',
			)
		);
		$image    = $this->factory->attachment->create_upload_object( __DIR__ . '/image-placeholder.jpg' );
		// Set ACF fields for the location: choctaw location but type is not 'Health Facility'.
		update_field( 'choctaw_or_external_location', 'choctaw', $location->ID );
		update_field( 'type', 'Commerce', $location->ID );
		update_field( 'photo', $image, $location->ID );
		update_field( 'address', '456 Other St', $location->ID );
		update_field( 'city_state_zip', 'Othercity, OC 67890', $location->ID );
		update_field( 'phone_number', '555-0202', $location->ID );

		$payload = $this->payload_factory->create_payload( $location );
		$this->assertIsArray( $payload );
		$this->assertCount( 1, $payload );

		$entry = $payload[0];
		$this->assertArrayHasKey( 'location_type', $entry );
		$this->assertEquals( 'external', $entry['location_type'] );
	}
}
