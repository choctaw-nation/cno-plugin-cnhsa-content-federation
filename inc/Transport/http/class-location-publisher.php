<?php
/**
 * Class Location_Publisher
 *
 * @package ChoctawNation\CNHSA_Federation\Http
 */

namespace ChoctawNation\CNHSA_Federation\Transport\Http;

/**
 * Location Publisher
 */
class Location_Publisher extends Abstract_Publisher {
	/**
	 * Publishes location data to the configured endpoint.
	 *
	 * @param array $data The location data to publish.
	 * @return array The response from the API, or an error message on failure.
	 */
	public function publish_location( array $data ): array {
		$endpoint = $this->base_url . '/locations';
		$auth     = $this->get_auth();

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . $auth,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $data ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'data'    => json_decode( wp_remote_retrieve_body( $response ), true ),
		);
	}
}
