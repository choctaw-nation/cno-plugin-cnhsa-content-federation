<?php
/**
 * ID Resolver Tests
 *
 * @package ChoctawNation\CNHSA_Federation\Tests
 */

namespace ChoctawNation\CNHSA_Federation\Tests;

use WP_UnitTestCase;
use ChoctawNation\CNHSA_Federation\WP\ID_Resolver;
use ChoctawNation\CNHSA_Federation\WP\Notifier;

/**
 * Class Test_ID_Resolver
 */
class Test_ID_Resolver extends WP_UnitTestCase {
	/**
	 * The ID Resolver instance.
	 *
	 * @var ID_Resolver $resolver
	 */
	private ID_Resolver $resolver;

	/**
	 * Dummy posts created for testing pagination in API responses.
	 *
	 * @var array $dummy_posts
	 */
	private static array $dummy_posts;

	/**
	 * Set Up the test environment.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		$post_types = array( 'services', 'location' );
		foreach ( $post_types as $post_type ) {
			register_post_type(
				$post_type,
				array(
					'public' => true,
				)
			);
		}
		self::$dummy_posts = self::factory()->post->create_many( 150 );
	}

	/**
	 * Tear down the test environment.
	 */
	public static function tear_down_after_class(): void {
		unregister_post_type( 'services' );
		unregister_post_type( 'location' );
		parent::tear_down_after_class();
	}

	/**
	 * Set up the tests.
	 */
	public function set_up(): void {
		parent::set_up();
		$notifier       = $this->getMockBuilder( Notifier::class )
			->disableOriginalConstructor()
			->getMock();
		$this->resolver = new ID_Resolver( 'https://example.com/api', $notifier );
	}

	/**
	 * Test that the resolver correctly retrieves the CNHSA ID from post meta.
	 */
	public function test_get_cnhsa_id_by_post_meta() {
		$post = $this->factory->post->create_and_get();
		update_post_meta( $post->ID, 'cnhsa_services_id', 123 );
		$this->resolver->find_cnhsa_id( 'services', $post );
		$this->assertEquals( 123, get_post_meta( $post->ID, 'cnhsa_services_id', true ) );
	}

	/**
	 * Test that the resolver correctly retrieves the CNHSA ID via the API when not found in post meta.
	 *
	 * @dataProvider data_service_http_filters
	 * @param callable $callback The callback to use for filtering HTTP requests.
	 */
	public function test_set_service_cnhsa_id_to_postmeta_via_api( $callback ) {
		$service_post = $this->factory->post->create_and_get(
			array(
				'post_name' => 'test-service',
				'post_type' => 'services',
			)
		);
		HTTP_Requests::custom_callback_request( $callback );
		$this->resolver->find_cnhsa_id( 'services', $service_post );
		HTTP_Requests::clear_filters();
		$this->assertEquals( 123, get_post_meta( $service_post->ID, 'cnhsa_services_id', true ) );
	}

	/**
	 * Provides http filters to simulate different API responses for service ID resolution tests.
	 *
	 * @return array The array of test cases with their corresponding HTTP request filters.
	 */
	public function data_service_http_filters(): array {
		$successful_data = array(
			array(
				'id'    => 123,
				'slug'  => 'test-service',
				'title' => array( 'rendered' => 'Test Service' ),
			),
		);

		return array(
			'post is service page 1' => array(
				fn() => HTTP_Requests::generate_response_array(
					$successful_data,
					200,
					array( 'X-WP-TotalPages' => 1 )
				),
			),
			'post is service page 2' => array(
				function ( $x, $y, $url ) use ( $successful_data ) {
					$page = $this->parse_page_param( $url );
					$posts = 2 === $page ? $successful_data : $this->paginate_posts( $url );
					return HTTP_Requests::generate_response_array( $posts, 200, array( 'X-WP-TotalPages' => 2 ) );
				},
			),
		);
	}

	/**
	 * Test that the resolver correctly retrieves the CNHSA ID for a location via the API when not found in post meta.
	 *
	 * @dataProvider data_location_http_filters
	 * @param callable $callback The callback to use for filtering HTTP requests.
	 */
	public function test_set_location_cnhsa_id_to_postmeta_via_api( $callback ) {
		$location_post = $this->factory->post->create_and_get(
			array(
				'post_name' => 'test-location',
				'post_type' => 'location',
			)
		);
		HTTP_Requests::custom_callback_request( $callback );
		$this->resolver->find_cnhsa_id( 'location', $location_post );
		HTTP_Requests::clear_filters();
		$this->assertEquals( 456, get_post_meta( $location_post->ID, 'cnhsa_location_id', true ) );
	}

	/**
	 * Provides http filters to simulate different API responses for location ID resolution tests.
	 */
	public function data_location_http_filters(): array {
		$successful_data = array(
			array(
				'id'    => 456,
				'slug'  => 'test-location',
				'title' => array( 'rendered' => 'Test Location' ),
			),
		);

		return array(
			'post is clinic page 1'              => array(
				fn() => HTTP_Requests::generate_response_array(
					$successful_data,
					200,
					array( 'X-WP-TotalPages' => 1 )
				),
			),
			'post is clinic page 2'              => array(
				function ( $x, $y, $url ) use ( $successful_data ) {
					$page = $this->parse_page_param( $url );
					$posts = 2 === $page ? $successful_data : $this->paginate_posts( $url );
					return HTTP_Requests::generate_response_array( $posts, 200, array( 'X-WP-TotalPages' => 2 ) );
				},
			),
			'post is additional-facility page 1' => array(
				function ( $x, $y, $url ) use ( $successful_data ) {
					$post_type = $this->get_post_type( $url );
					if ( 'clinic' === $post_type ) {
						return HTTP_Requests::generate_response_array( array(), 200, array( 'X-WP-TotalPages' => 1 ) );
					}
					return HTTP_Requests::generate_response_array( $successful_data, 200, array( 'X-WP-TotalPages' => 1 ) );
				},
			),
			'post is additional-facility page 1 with paged clinic response' => array(
				function ( $x, $y, $url ) use ( $successful_data ) {
					$post_type = $this->get_post_type( $url );
					if ( 'clinic' === $post_type ) {
						return HTTP_Requests::generate_response_array( $this->paginate_posts( $url ), 200, array( 'X-WP-TotalPages' => 2 ) );
					}
					return HTTP_Requests::generate_response_array( $successful_data, 200, array( 'X-WP-TotalPages' => 1 ) );
				},
			),
			'post is additional-facility page 2 with paged clinic response' => array(
				function ( $x, $y, $url ) use ( $successful_data ) {
					$post_type = $this->get_post_type( $url );
					if ( 'clinic' === $post_type ) {
						return HTTP_Requests::generate_response_array( $this->paginate_posts( $url ), 200, array( 'X-WP-TotalPages' => 2 ) );
					}
					$page = $this->parse_page_param( $url );
					$posts = 2 === $page ? $successful_data : $this->paginate_posts( $url );
					return HTTP_Requests::generate_response_array( $posts, 200, array( 'X-WP-TotalPages' => 2 ) );
				},
			),
		);
	}

	/**
	 * Parse post type from URL for use in HTTP request filters.
	 *
	 * @param string $url The URL being requested.
	 * @return 'clinic'|'additional-facility' The post type parsed from the URL.
	 */
	private function get_post_type( string $url ): string {
		$params = wp_parse_url( $url );
		$path   = explode( '/', $params['path'] );
		return array_pop( $path );
	}

	/**
	 * Slices a posts array into pages based on the 'page' and 'per_page' query parameters in the URL.
	 *
	 * @param string $url The URL containing the pagination query parameters.
	 * @return array The sliced array of posts for the current page.
	 */
	private function paginate_posts( string $url ): array {
		$params   = wp_parse_url( $url );
		$query    = wp_parse_args( $params['query'] ?? '' );
		$page     = (int) ( $query['page'] ?? 1 );
		$per_page = (int) ( $query['per_page'] ?? 100 );
		$posts    = array_map(
			function ( $post_id ) {
				$post = get_post( $post_id );
				return array(
					'id'    => $post_id,
					'slug'  => $post->post_name,
					'title' => array( 'rendered' => $post->post_title ),
				);
			},
			self::$dummy_posts
		);
		return array_slice( $posts, ( $page - 1 ) * $per_page, $per_page );
	}

	/**
	 * Parses the 'page' query parameter from a URL.
	 *
	 * @param string $url The URL containing the pagination query parameters.
	 * @return int The page number.
	 */
	private function parse_page_param( string $url ): int {
		$params = wp_parse_url( $url );
		$query  = wp_parse_args( $params['query'] ?? '' );
		return (int) ( $query['page'] ?? 1 );
	}
}
