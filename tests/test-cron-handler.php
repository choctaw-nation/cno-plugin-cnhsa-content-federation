<?php
/**
 * Cron Handler Tests
 *
 * @package ChoctawNation\CNHSA_Federation\Tests
 */

namespace ChoctawNation\CNHSA_Federation\Tests;

use ChoctawNation\CNHSA_Federation\WP\Cron_Handler;
use ChoctawNation\CNHSA_Federation\WP\Scheduler;
use ChoctawNation\CNHSA_Federation\Transport\Http\Service_Publisher;
use ChoctawNation\CNHSA_Federation\Transport\Http\Location_Publisher;
use WP_UnitTestCase;

/**
 * Class Test_Cron_Handler
 */
class Test_Cron_Handler extends WP_UnitTestCase {
	/**
	 * Instance
	 *
	 * @var Cron_Handler $cron_handler
	 */
	private Cron_Handler $cron_handler;

	/**
	 * Scheduler instance for testing the wiring of hooks.
	 *
	 * @var Scheduler $scheduler
	 */
	private $scheduler;

	/**
	 * Service publisher mock for testing the wiring of hooks.
	 *
	 * @var Service_Publisher $service_publisher
	 */
	private $service_publisher;

	/**
	 * Location publisher mock for testing the wiring of hooks.
	 *
	 * @var Location_Publisher $location_publisher
	 */
	private $location_publisher;

	/**
	 * Set up custom post types for testing.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		register_post_type( 'services', array( 'public' => true ) );
		register_post_type( 'location', array( 'public' => true ) );
	}

	/**
	 * Clean up after tests.
	 */
	public static function tear_down_after_class(): void {
		unregister_post_type( 'services' );
		unregister_post_type( 'location' );
		parent::tear_down_after_class();
	}

	/**
	 * Set up the test environment.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->scheduler          = $this->getMockBuilder( Scheduler::class )
		->onlyMethods( array( 'schedule_services_update', 'schedule_locations_update' ) )
		->getMock();
		$this->location_publisher = $this->getMockBuilder( Location_Publisher::class )
		->disableOriginalConstructor()
		->getMock();
		$this->service_publisher  = $this->getMockBuilder( Service_Publisher::class )
		->disableOriginalConstructor()
		->onlyMethods( array( 'update_service', 'create_service' ) )
		->getMock();
		$this->cron_handler       = new Cron_Handler( $this->scheduler, $this->service_publisher, $this->location_publisher );
	}
	/**
	 * Test that the cron handler class can be instantiated and has the expected methods.
	 *
	 * @dataProvider data_hooks_and_callbacks
	 * @param string $hook The name of the hook to check.
	 * @param string $callback The name of the expected callback method.
	 */
	public function test_cron_handler_wires_scheduler_hooks( string $hook, string $callback ) {
		$this->cron_handler->wire_callbacks();
		$this->assertIsInt( has_action( $hook, array( $this->scheduler, $callback ) ) );
	}

	/**
	 * Data provider for testing that the cron handler hooks are connected to the expected callbacks.
	 *
	 * @return array Test cases with hook names and expected callback methods.
	 */
	public function data_hooks_and_callbacks() {
		return array(
			'save service cpt'  => array( 'save_post_services', 'schedule_services_update' ),
			'save location cpt' => array( 'save_post_location', 'schedule_locations_update' ),
		);
	}

	/**
	 * Test that the save_post_services hook triggers the schedule_services_update method in the Scheduler class.
	 *
	 * @dataProvider data_save_post_hooks_and_callbacks
	 * @param string $method The name of the Scheduler method expected to be called.
	 * @param string $hook The name of the hook to trigger.
	 */
	public function test_save_post_hook_fires_correct_cron( $method, $hook ) {
		$this->cron_handler->wire_callbacks();
		$this->scheduler->expects( $this->once() )
		->method( $method );

		$post_types = array( 'services', 'location' );
		foreach ( $post_types as $post_type ) {
			self::factory()->post->create( array( 'post_type' => $post_type ) );
		}
	}


	/**
	 * Data provider for testing that the cron handler hooks are connected to the expected callbacks.
	 *
	 * @return array Test cases with hook names and expected callback methods.
	 */
	public function data_save_post_hooks_and_callbacks() {
		return array(
			'services update' => array( 'schedule_services_update', 'save_post_services' ),
			'location update' => array( 'schedule_locations_update', 'save_post_location' ),
		);
	}
}
