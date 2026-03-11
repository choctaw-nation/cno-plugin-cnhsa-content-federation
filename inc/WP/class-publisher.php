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
	 * Location endpoint.
	 *
	 * @var string $location_endpoint
	 */
	public string $location_endpoint;

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
		$this->location_endpoint        = "{$this->gateway->base_url}/{$this->gateway->endpoint}/location";
	}

	/**
	 * Federate services post to CNHSA endpoint
	 *
	 * @param WP_Post $service_post The service post object to be published.
	 * @throws Exception If publishing the service fails.
	 */
	public function update_services( WP_Post $service_post ): void {
		$service_url  = "{$this->gateway->base_url}/{$this->gateway->endpoint}/service";
		$location_ids = array();
		try {
			$location_payloads = $this->build_location_payload( $service_post );
			if ( ! empty( $location_payloads ) ) {
				$locations = array_map(
					function ( $location ) use ( &$location_ids ) {
						$location_ids[] = (int) $location['cno_location_id'];
						return get_post( $location['cno_location_id'] );
					},
					$location_payloads
				);
				foreach ( $locations as $location_post ) {
					$this->update_locations( $location_post );
				}
			}
			$id               = $this->id_resolver->find_cnhsa_id( $service_post->post_type, $service_post, $this->gateway->base_url );
			$service_endpoint = $service_url . ( $id ? "/{$id}" : '' );
			$service_payload  = $this->service_payload_factory->create_payload( $service_post );
			if ( is_wp_error( $service_payload ) ) {
				throw new Exception( esc_textarea( 'Building service payload failed: ' . $service_payload->get_error_message() ) );
			}
			if ( ! empty( $location_ids ) ) {
				$service_payload['location_data'] = $location_ids;
			}
			$service_data = $this->gateway->publish_content( $service_endpoint, $service_payload );
			update_post_meta( $service_post->ID, 'cnhsa_id', $service_data['data']['id'] );
		} catch ( Exception $e ) {
			$this->notifier->notify( 'CNHSA Services Federation Failed', esc_textarea( 'Publishing service post failed: ' . $e->getMessage() ) );
		}
	}

	/**
	 * Federate location post to CNHSA endpoint
	 *
	 * @param WP_Post $location_post The location post object to be published.
	 * @throws Exception If publishing the location fails.
	 */
	public function update_locations( WP_Post $location_post ): void {
		try {
			$id               = $this->id_resolver->find_cnhsa_id( $location_post->post_type, $location_post, $this->gateway->base_url );
			$url              = $this->location_endpoint . ( $id ? "/{$id}" : '' );
			$location_payload = $this->build_location_payload( $location_post );
			if ( is_null( $location_payload ) ) {
				throw new Exception( esc_html( 'No location payload to publish.' ) );
			}
			$payload = $location_payload[0];
			$data    = $this->gateway->publish_content( $url, $payload );
			update_post_meta( $location_post->ID, 'cnhsa_id', $data['data']['id'] );
		} catch ( Exception $e ) {
			$this->notifier->notify( 'CNHSA Locations Federation Failed', esc_textarea( 'Publishing location post failed: ' . $e->getMessage() ) );
		}
	}

	/**
	 * Builds the location payload for the given post.
	 *
	 * @param WP_Post $post The post object to build the location payload for.
	 * @return ?array The location payload array, or null if no payload is needed.
	 * @throws Exception If building the location payload fails.
	 */
	private function build_location_payload( WP_Post $post ): ?array {
		$location_payload = $this->location_payload_factory->create_payload( $post );
		if ( is_wp_error( $location_payload ) ) {
			throw new Exception( esc_html( 'Building location payload failed: ' . $location_payload->get_error_message() ) );
		}
		return $location_payload;
	}
}
