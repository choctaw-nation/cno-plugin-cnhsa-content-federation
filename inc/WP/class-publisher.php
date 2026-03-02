<?php
/**
 * Publisher
 * Handles Publishing job orchestration for Services and Locations.
 *
 * @package CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation\WP;

use ChoctawNation\CNHSA_Federation\Transport\HTTP_Gateway;
use ChoctawNation\CNHSA_Federation\WP\Payload\Location_Payload_Factory;
use ChoctawNation\CNHSA_Federation\WP\Payload\Service_Payload_Factory;
use Exception;
use WP_Error;
use WP_Post;

/**
 * Class Publisher
 * Handles Publishing job orchestration for Services and Locations.
 */
class Publisher {
	/**
	 * ID Resolver instance.
	 *
	 * @var ID_Resolver $id_resolver
	 */
	public ID_Resolver $id_resolver;

	/**
	 * HTTP Transport Gateway instance.
	 *
	 * @var HTTP_Gateway
	 */
	public HTTP_Gateway $gateway;

	/**
	 * Service Payload Factory instance.
	 *
	 * @var Service_Payload_Factory
	 */
	public Service_Payload_Factory $service_payload_factory;

	/**
	 * Location Payload Factory instance.
	 *
	 * @var Location_Payload_Factory
	 */
	public Location_Payload_Factory $location_payload_factory;

	/**
	 * Constructor
	 *
	 * @param ID_Resolver              $id_resolver              An instance of the ID_Resolver class to resolve post IDs.
	 * @param HTTP_Gateway             $gateway                  An instance of the Gateway class to handle HTTP communication.
	 * @param Service_Payload_Factory  $service_payload_factory An instance of the Service_Payload_Factory class to create service payloads.
	 * @param Location_Payload_Factory $location_payload_factory An instance of the Location_Payload_Factory class to create location payloads.
	 */
	public function __construct( ID_Resolver $id_resolver, HTTP_Gateway $gateway, Service_Payload_Factory $service_payload_factory, Location_Payload_Factory $location_payload_factory ) {
		$this->id_resolver              = $id_resolver;
		$this->gateway                  = $gateway;
		$this->service_payload_factory  = $service_payload_factory;
		$this->location_payload_factory = $location_payload_factory;
	}

	public function update_services( WP_Post $post ) {
		try {
			$id      = $this->id_resolver->find_cnhsa_id( $post->post_type, $post, $this->gateway->base_url );
			$payload = $this->build_payload( $post );
			if ( is_wp_error( $payload ) ) {
				// Handle error appropriately, e.g., log it or notify.
				return;
			}
			$url = $id ? "{$this->gateway->base_url}/service/{$id}" : "{$this->gateway->base_url}/service";
			$this->gateway->publish_content( $url, $payload );
		} catch ( Exception $e ) {
		}
	}
	public function update_location( WP_Post $post ) {
		// to do
	}

	/**
	 * Builds the payload for the service post, including location data.
	 *
	 * @param WP_Post $post The service post object for which to build the payload.
	 * @return array|WP_Error The combined payload array or a WP_Error if payload creation fails.
	 */
	private function build_payload( WP_Post $post ): array|WP_Error {
		$payload = $this->service_payload_factory->create_payload( $post );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
		$location_payload = $this->location_payload_factory->create_payload( $post );
		if ( is_wp_error( $location_payload ) ) {
			return $location_payload;
		}
		return is_null( $location_payload ) ? $payload : array_merge( $payload, array( 'location_data' => $location_payload ) );
	}
}
