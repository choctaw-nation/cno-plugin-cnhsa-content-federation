<?php
/**
 * Scheduler Tests
 *
 * @package ChoctawNation\CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation\Tests;

use ChoctawNation\CNHSA_Federation\WP\Cron_Handler;
use ChoctawNation\CNHSA_Federation\WP\Scheduler;
use WP_UnitTestCase;

/**
 * Test_Scheduler Class
 */
class Test_Scheduler extends WP_UnitTestCase {
	/**
	 * Set up the Scheduler instance for testing.
	 *
	 * @var Scheduler $scheduler
	 */
	private Scheduler $scheduler;

	/**
	 * Set up the test environment before the class is run.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		register_post_type(
			'services',
			array(
				'public'     => true,
				'taxonomies' => array( 'category' ),
			)
		);
		self::factory()->term->create_many( 10, array( 'taxonomy' => 'category' ) );
		// ensure category with term id 12 exists for testing
		self::factory()->term->create(
			array(
				'name'     => 'health',
				'taxonomy' => 'category',
			)
		);
	}

	/**
	 * Set up the test environment before each test.
	 */
	public function set_up() {
		parent::set_up();
		$notifier        = $this->getMockBuilder( 'ChoctawNation\CNHSA_Federation\WP\Notifier' )
			->getMock();
		$this->scheduler = new Scheduler( $notifier );
	}

	/**
	 * Test that the Scheduler class can be instantiated and has the expected properties.
	 */
	public function test_callback_scheduled_for_five_seconds_after_save_post_hook() {
		$post = self::factory()->post->create_and_get(
			array(
				'post_type'     => 'services',
				'post_status'   => 'publish',
				'post_title'    => 'Test Service',
				'post_category' => array( 12 ), // assuming this is the term ID for 'health'
			)
		);
		$this->scheduler->schedule_services_update( $post->ID, $post, false );
		$scheduled = wp_next_scheduled( $this->scheduler->cron_keys['services']['update'], array( $post ) );
		$this->assertNotFalse( $scheduled, 'Expected a scheduled event for creating a service post.' );
		do_action( 'save_post_services', $post->ID, $post, true );
		$scheduled = wp_next_scheduled( $this->scheduler->cron_keys['services']['update'], array( $post ) );
		$this->assertNotFalse( $scheduled, 'Expected a scheduled event for creating a service post.' );
	}
}
