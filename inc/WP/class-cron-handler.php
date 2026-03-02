<?php
/**
 * Cron Registrar
 *
 * @package Choctawnation\CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation\WP;

use ChoctawNation\CNHSA_Federation\WP\Publisher;

/**
 * Class Cron_Handler
 */
class Cron_Handler {
	/**
	 * Scheduler instance for handling the scheduling of tasks.
	 *
	 * @var Scheduler $scheduler
	 */
	private Scheduler $scheduler;

	/**
	 * Service Publisher instance for handling service publishing tasks.
	 *
	 * @var Publisher $publisher
	 */
	private Publisher $publisher;

	/**
	 * Constructor
	 *
	 * @param Scheduler $scheduler An instance of the Scheduler class to handle scheduling of tasks.
	 * @param Publisher $publisher An instance of the Publisher class to handle publishing tasks.
	 */
	public function __construct( Scheduler $scheduler, Publisher $publisher ) {
		$this->scheduler = $scheduler;
		$this->publisher = $publisher;
	}

	/**
	 * Wire callbacks to WordPress actions and filters.
	 */
	public function wire_callbacks() {
		$this->wire_save_post_hook_callbacks();
		$this->wire_cron_hook_callbacks();
	}

	/**
	 * `save_post` handlers -> wire async callbacks for services and locations
	 */
	private function wire_save_post_hook_callbacks() {
		$wiring = array(
			'schedule_services_update'  => 'services',
			'schedule_locations_update' => 'location',
		);
		foreach ( $wiring as $method => $post_type ) {
			add_action( "save_post_{$post_type}", array( $this->scheduler, $method ), 10, 3 );
		}
	}

	/**
	 * Cron hooks' callbacks (powered by transporters)
	 */
	private function wire_cron_hook_callbacks() {
		$post_types = array( 'services', 'location' );
		foreach ( $post_types as $post_type ) {
			$hook   = $this->scheduler->cron_keys[ $post_type ]['update'];
			$method = 'update_' . $post_type;
			add_action( $hook, array( $this->publisher, $method ), 10, 2 );
		}
	}
}
