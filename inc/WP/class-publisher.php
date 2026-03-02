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
	 * Notifier instance.
	 *
	 * @var Notifier $notifier
	 */
	public Notifier $notifier;

	/**
	 * Constructor
	 *
	 * @param ID_Resolver              $id_resolver              An instance of the ID_Resolver class to resolve post IDs.
	 * @param HTTP_Gateway             $gateway                  An instance of the Gateway class to handle HTTP communication.
	 * @param Service_Payload_Factory  $service_payload_factory An instance of the Service_Payload_Factory class to create service payloads.
	 * @param Location_Payload_Factory $location_payload_factory An instance of the Location_Payload_Factory class to create location payloads.
	 * @param Notifier                 $notifier                 An instance of the Notifier class to handle notifications.
	 */
	public function __construct( ID_Resolver $id_resolver, HTTP_Gateway $gateway, Service_Payload_Factory $service_payload_factory, Location_Payload_Factory $location_payload_factory, Notifier $notifier ) {
		$this->id_resolver              = $id_resolver;
		$this->gateway                  = $gateway;
		$this->service_payload_factory  = $service_payload_factory;
		$this->location_payload_factory = $location_payload_factory;
		$this->notifier                 = $notifier;
	}

	/**
	 * Federate services post to CNHSA endpoint
	 *
	 * @param WP_Post $post The service post object to be published.
	 */
	public function update_services( WP_Post $post ): void {
		try {
			$id   = $this->id_resolver->find_cnhsa_id( $post->post_type, $post, $this->gateway->base_url );
			$url  = $id ? "{$this->gateway->base_url}/service/{$id}" : "{$this->gateway->base_url}/service";
			$data = $this->update_post( $url, $post );
			update_post_meta( $post->ID, 'cnhsa_id', $data['id'] );
		} catch ( Exception $e ) {
			$this->notifier->notify( 'CNHSA Services Federation Failed', esc_textarea( 'Publishing service post failed: ' . $e->getMessage() ) );
		}
	}

	/**
	 * Federate location post to CNHSA endpoint
	 *
	 * @param WP_Post $post The location post object to be published.
	 */
	public function update_location( WP_Post $post ) {
		try {
			$id   = $this->id_resolver->find_cnhsa_id( $post->post_type, $post, $this->gateway->base_url );
			$url  = $id ? "{$this->gateway->base_url}/location/{$id}" : "{$this->gateway->base_url}/location";
			$data = $this->update_post( $url, $post );
			update_post_meta( $post->ID, 'cnhsa_id', $data['id'] );
		} catch ( Exception $e ) {
			$this->notifier->notify( 'CNHSA Locations Federation Failed', esc_textarea( 'Publishing location post failed: ' . $e->getMessage() ) );
		}
	}

	/**
	 * Updates the post content at the specified URL.
	 *
	 * @param string  $url  The endpoint URL to which the content should be published.
	 * @param WP_Post $post The post object containing the content to be published.
	 * @return array The response data from the publish_content method.
	 * @throws Exception If building the payload fails.
	 */
	private function update_post( string $url, WP_Post $post ): array {
		$payload = $this->build_payload( $post );
		if ( is_wp_error( $payload ) ) {
			throw new Exception( esc_textarea( 'Building payload failed: ' . $payload->get_error_message() ) );
		}
		return $this->gateway->publish_content( $url, $payload );
	}

	/**
	 * Builds the payload for the service post, including location data.
	 *
	 * @param WP_Post $post The service post object for which to build the payload.
	 * @return array|WP_Error The combined payload array or a WP_Error if payload creation fails.
	 */
	private function build_payload( WP_Post $post ): array|WP_Error {
		$service_payload = $this->service_payload_factory->create_payload( $post );
		if ( is_wp_error( $service_payload ) ) {
			return $service_payload;
		}
		$location_payload = $this->location_payload_factory->create_payload( $post );
		if ( is_wp_error( $location_payload ) ) {
			return $location_payload;
		}
		if ( 'services' === $post->post_type ) {
			return is_null( $location_payload ) ? $service_payload : array_merge( $service_payload, array( 'location_data' => $location_payload ) );
		} elseif ( 'locations' === $post->post_type ) {
			return $location_payload;
		} else {
			return new WP_Error( 'invalid_post_type', 'Unsupported post type for payload creation.' );
		}
	}
}
