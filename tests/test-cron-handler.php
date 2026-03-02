<?php
/**
 * Cron Handler Tests
 *
 * @package ChoctawNation\CNHSA_Federation\Tests
 */

namespace ChoctawNation\CNHSA_Federation\Tests;

use ChoctawNation\CNHSA_Federation\WP\Cron_Handler;
use ChoctawNation\CNHSA_Federation\WP\ID_Resolver;
use ChoctawNation\CNHSA_Federation\WP\Scheduler;
use ChoctawNation\CNHSA_Federation\WP\Publisher;
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
	 * Service Publisher instance for testing the wiring of hooks.
	 *
	 * @var Service_Publisher $service_publisher
	 */
	private Publisher $publisher;

	/**
	 * Set up custom post types for testing.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Test_Utils::setup_post_types();
	}

	/**
	 * Set up the test environment.
	 */
	public function set_up(): void {
		parent::set_up();
		$notifier           = $this->getMockBuilder( \ChoctawNation\CNHSA_Federation\WP\Notifier::class )
		->disableOriginalConstructor()
		->getMock();
		$this->scheduler    = $this->getMockBuilder( Scheduler::class )
		->onlyMethods( array( 'schedule_services_update', 'schedule_locations_update' ) )
		->setConstructorArgs( array( $notifier ) )
		->getMock();
		$this->publisher    = $this->getMockBuilder( Publisher::class )
		->onlyMethods( array( 'update_services', 'update_location' ) )
		->disableOriginalConstructor()
		->getMock();
		$this->cron_handler = new Cron_Handler( $this->scheduler, $this->publisher );
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
	 */
	public function test_save_post_hook_fires_correct_cron( $method ) {
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
			'services update' => array( 'schedule_services_update' ),
			'location update' => array( 'schedule_locations_update' ),
		);
	}

	/**
	 * Test that the service publisher's create_service method is called when the corresponding cron hook is triggered.
	 */
	public function test_publisher_methods_fire_on_cron_trigger() {
		$this->cron_handler->wire_callbacks();
		// Set up credentials so that publisher methods are called.
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
		$this->publisher->expects( $this->once() )
		->method( 'update_services' );
		$post = self::factory()->post->create_and_get( array( 'post_type' => 'services' ) );
		do_action( $this->scheduler->cron_keys['services']['update'], $post );
		// Tear down credentials.
		update_option(
			'cnhsa_federation_options',
			array()
		);
		HTTP_Requests::clear_filters();
	}
}
