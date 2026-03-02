<?php
/**
 * Class: Service Publisher
 * Handles data operations between the CNO & CNHSA themes.
 *
 * @package ChoctawNation
 * @subpackage CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation\Transport\Http;

use ChoctawNation\CNHSA_Federation\WP\ID_Resolver;
use ChoctawNation\CNHSA_Federation\WP\Notifier;
use ChoctawNation\CNHSA_Federation\WP\Payload\Location_Payload_Factory;
use ChoctawNation\CNHSA_Federation\WP\Payload\Service_Payload_Factory;
use WP_Error;
use WP_Post;

/**
 * Class Service_Publisher
 * Manages the data operations for the CNHSA Federation.
 */
class Service_Publisher extends Abstract_Publisher {
	/**
	 * The Location Payload Factory instance.
	 *
	 * @var Location_Payload_Factory $location_payload_factory
	 */
	private Location_Payload_Factory $location_payload_factory;

	/**
	 * Service_Publisher constructor.
	 *
	 * @param string                   $environment The CNHSA environment (e.g., 'production', 'staging').
	 * @param ID_Resolver              $id_resolver The ID Resolver instance for mapping post IDs.
	 * @param Service_Payload_Factory  $service_payload_factory The Service Payload Factory instance for creating payloads.
	 * @param Notifier                 $notifier The Notifier instance for sending notifications on errors or important events.
	 * @param Location_Payload_Factory $location_payload_factory The Location Payload Factory instance for creating location payloads.
	 */
	public function __construct( string $environment, ID_Resolver $id_resolver, Service_Payload_Factory $service_payload_factory, Notifier $notifier, Location_Payload_Factory $location_payload_factory ) {
		parent::__construct( $environment, $id_resolver, $service_payload_factory, $notifier );
		$this->location_payload_factory = $location_payload_factory;
	}
	/**
	 * Publishes the service content to the CNHSA Environment.
	 *
	 * @param WP_Post $post The service post object to be published.
	 */
	public function publish_content( WP_Post $post ): void {
		$post_id = $this->id_resolver->find_cnhsa_id( 'services', $post );
		$url     = $post_id ? "{$this->base_url}/service/{$post_id}" : "{$this->base_url}/service";
		$payload = $this->build_payload( $post );
		if ( is_wp_error( $payload ) ) {
			$this->notifier->notify(
				'CNHSA Federation Payload Error',
				sprintf(
					'Error creating payload for post ID %d: %s',
					$post->ID,
					$payload->get_error_message()
				)
			);
			return;
		}
		$response      = wp_remote_post(
			$url,
			array(
				'method'  => 'POST',
				'body'    => wp_json_encode( $payload ),
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Basic ' . $this->get_auth(),
				),
			)
		);
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( is_wp_error( $response ) || 201 !== $response_code ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$this->notifier->notify(
				'CNHSA Federation API Error',
				sprintf(
					'%s error: %s',
					$body['code'] ?? $response_code,
					$body['message'] ?? $body['error']
				)
			);
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $body['data']['id'] ) ) {
			update_post_meta( $post->ID, 'cnhsa_services_id', (int) $body['data']['id'] );
		}
	}

	/**
	 * Builds the payload for the service post, including location data.
	 *
	 * @param WP_Post $post The service post object for which to build the payload.
	 * @return array|WP_Error The combined payload array or a WP_Error if payload creation fails.
	 */
	private function build_payload( WP_Post $post ): array|WP_Error {
		$payload = $this->payload_factory->create_payload( $post );
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
