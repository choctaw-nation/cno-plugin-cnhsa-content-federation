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
	 * @var Service_Publisher $service_publisher
	 */
	private Service_Publisher $service_publisher;

	/**
	 * Location publisher instance for handling location updates.
	 *
	 * @var Location_Publisher $location_publisher
	 */
	private Location_Publisher $location_publisher;

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
	 * @param Service_Publisher  $service_publisher An instance of the Service_Publisher class to handle service updates and creations.
	 * @param Location_Publisher $location_publisher An instance of the Location_Publisher class to handle location updates.
	 */
	public function __construct( Scheduler $scheduler, Service_Publisher $service_publisher, Location_Publisher $location_publisher ) {
		$this->service_publisher  = $service_publisher;
		$this->location_publisher = $location_publisher;
		$this->scheduler          = $scheduler;
	}

	/**
	 * Wire callbacks to WordPress actions and filters.
	 */
	public function wire_callbacks() {
		// save_post handlers -> wire async callbacks for services and locations
		add_action( 'save_post_services', array( $this->scheduler, 'schedule_services_update' ) );
		add_action( 'save_post_locations', array( $this->scheduler, 'schedule_location_update' ) );

		// cron hooks -> powered by transporters
		add_action( $this->scheduler->cron_keys['services']['update'], array( $this->service_publisher, 'update_service' ) );
		add_action( $this->scheduler->cron_keys['services']['create'], array( $this->service_publisher, 'create_service' ) );
		add_action( $this->scheduler->cron_keys['locations']['update'], array( $this->location_publisher, 'update_health_location' ) );
	}
}
