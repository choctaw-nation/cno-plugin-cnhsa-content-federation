<?php
/**
 * Service Payload Factory Tests
 *
 * @package ChoctawNation\CNHSA_Federation\Tests
 */

namespace ChoctawNation\CNHSA_Federation\Tests;

use ChoctawNation\CNHSA_Federation\WP\Payload\Service_Payload_Factory;
use WP_UnitTestCase;

/**
 * Class Test Service Payload Factory
 *
 * @package ChoctawNation\CNHSA_Federation\Tests
 */
class Test_Service_Payload_Factory extends WP_UnitTestCase {
	/**
	 * The payload factory instance.
	 *
	 * @var Service_Payload_Factory $payload_factory
	 */
	private Service_Payload_Factory $payload_factory;

	/**
	 * The base keys expected in the payload.
	 *
	 * @var array $base_keys
	 */
	private array $base_keys;

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		Test_Utils::setup_post_types();
		register_taxonomy( 'category', 'services' );
		self::factory()->category->create_many( 10 );
		self::factory()->category->create( array( 'name' => 'Health' ) );
		ACF_Fields::register_fields();
	}

	/**
	 * Set up the test environment.
	 */
	public function set_up() {
		parent::set_up();
		$this->payload_factory = new Service_Payload_Factory();
		$this->base_keys       = array(
			'title',
			'status',
			'slug',
			'excerpt',
			'post_data',
		);
	}

	/**
	 * Test that create_payload returns null for non-service post types.
	 */
	public function test_create_payload_non_service_post_type_returns_null() {
		$post = $this->factory->post->create_and_get(
			array(
				'post_type'  => 'page',
				'post_title' => 'Test Page',
			)
		);

		$payload = $this->payload_factory->create_payload( $post );
		$this->assertNull( $payload, 'Expected null payload for non-service post type.' );
	}

	/**
	 * Test that create_payload returns expected keys for service post with no location or additional categories.
	 */
	public function test_service_with_no_location_or_additional_categories_returns_array_with_expected_keys() {
		$service = $this->factory->post->create_and_get(
			array(
				'post_type'  => 'services',
				'post_title' => 'Service A',
			)
		);
		$payload = $this->payload_factory->create_payload( $service );
		$this->assertIsArray( $payload );

		foreach ( $this->base_keys as $key ) {
			$this->assertArrayHasKey( $key, $payload, "Payload is missing expected key: $key" );
		}
	}

	/**
	 * Test that create_payload returns null for a services post with no locations set.
	 */
	public function test_create_payload_for_services_post_with_additional_categories_returns_array_with_expected_keys() {
		$service = $this->factory->post->create_and_get(
			array(
				'post_type'  => 'services',
				'post_title' => 'Empty Service',
			)
		);
		wp_set_post_terms( $service->ID, array( 1, 2, 3, 12 ), 'category' );

		$payload = $this->payload_factory->create_payload( $service );
		$this->assertIsArray( $payload );
		$keys = array_merge( $this->base_keys, array( 'additional_categories' ) );
		foreach ( $keys as $key ) {
			$this->assertArrayHasKey( $key, $payload, "Payload is missing expected key: $key" );
		}
	}

	/**
	 * Related media field when empty should produce an empty array in post_data.
	 */
	public function test_related_media_empty_returns_empty_array_in_post_data() {
		$service = $this->factory->post->create_and_get(
			array(
				'post_type'  => 'services',
				'post_title' => 'Service With No Media',
			)
		);
		$payload = $this->payload_factory->create_payload( $service );
		$this->assertIsArray( $payload );
		$this->assertArrayHasKey( 'post_data', $payload );
		$this->assertArrayHasKey( 'related_media', $payload['post_data'] );
		$this->assertIsArray( $payload['post_data']['related_media'] );
		$this->assertCount( 0, $payload['post_data']['related_media'] );
	}

	/**
	 * Related media entries should be transformed: `file_or_link` becomes `external_link`
	 * and `external_link` is set appropriately for link vs pdf entries.
	 */
	public function test_related_media_transforms_entries() {
		$service = $this->factory->post->create_and_get(
			array(
				'post_type'  => 'services',
				'post_title' => 'Service With Media',
			)
		);
		$media   = array(
			array(
				'link_text'     => 'Link One',
				'file_or_link'  => 'some_link',
				'internal_link' => 'https://example.com/page',
			),
			array(
				'link_text'    => 'PDF One',
				'file_or_link' => 'pdf_file',
				'pdf'          => 'https://example.com/doc.pdf',
			),
		);
		update_field( 'related_media', $media, $service->ID );
		$payload = $this->payload_factory->create_payload( $service );
		$this->assertIsArray( $payload );
		$related = $payload['post_data']['related_media'];
		$this->assertCount( 2, $related );
		$this->assertEquals( 'external_link', $related[0]['file_or_link'] );
		$this->assertEquals( 'https://example.com/page', $related[0]['external_link'] );
		$this->assertEquals( 'external_link', $related[1]['file_or_link'] );
		$this->assertEquals( 'https://example.com/doc.pdf', $related[1]['external_link'] );
	}
}
