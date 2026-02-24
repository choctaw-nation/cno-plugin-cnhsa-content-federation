<?php
/**
 * Cron Registrar
 *
 * @package Choctawnation\CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation\WP;

use ChoctawNation\CNHSA_Federation\Transport\Http\Location_Publisher;
use ChoctawNation\CNHSA_Federation\Transport\Http\Service_Publisher;

/**
 * Class Cron_Handler
 */
class Cron_Handler {
	/**
	 * Service publisher instance for handling service updates and creations.
	 *
	 * @var Service_Publisher $services_publisher
	 */
	private Service_Publisher $services_publisher;

	/**
	 * Location publisher instance for handling location updates.
	 *
	 * @var Location_Publisher $locations_publisher
	 */
	private Location_Publisher $locations_publisher;

	/**
	 * Scheduler instance for handling the scheduling of tasks.
	 *
	 * @var Scheduler $scheduler
	 */
	private Scheduler $scheduler;

	/**
	 * Constructor
	 *
	 * @param Scheduler          $scheduler An instance of the Scheduler class to handle scheduling of tasks.
	 * @param Service_Publisher  $services_publisher An instance of the Service_Publisher class to handle service updates and creations.
	 * @param Location_Publisher $locations_publisher An instance of the Location_Publisher class to handle location updates.
	 */
	public function __construct( Scheduler $scheduler, Service_Publisher $services_publisher, Location_Publisher $locations_publisher ) {
		$this->services_publisher  = $services_publisher;
		$this->locations_publisher = $locations_publisher;
		$this->scheduler           = $scheduler;
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
		$wiring = array(
			'services'  => array(
				'update' => 'update_service',
				'create' => 'create_service',
			),
			'locations' => array(
				'update' => 'update_health_location',
			),
		);
		foreach ( $wiring as $post_type => $actions ) {
			foreach ( $actions as $action => $callback ) {
				$hook      = $this->scheduler->cron_keys[ $post_type ][ $action ];
				$publisher = $post_type . '_publisher';
				add_action( $hook, array( $this->$publisher, $callback ) );
			}
		}
	}
}
