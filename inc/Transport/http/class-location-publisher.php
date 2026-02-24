<?php
/**
 * Class Location_Publisher
 *
 * @package ChoctawNation\CNHSA_Federation\Http
 */

namespace ChoctawNation\CNHSA_Federation\Transport\Http;

use WP_Post;

/**
 * Location Publisher
 */
class Location_Publisher extends Abstract_Publisher {
	public function get_cnhsa_id( WP_Post $post ): int {
		// Implementation for retrieving the CNHSA ID for a location post.
	}
	/**
	 * Publishes location data to the configured endpoint.
	 *
	 * @param WP_Post $data The location data to publish.
	 * @return void
	 */
	public function publish_content( WP_Post $data ): void {
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
			// return array(
			// 'success' => false,
			// 'message' => $response->get_error_message(),
			// );
		}

		// return array(
		// 'success' => true,
		// 'data'    => json_decode( wp_remote_retrieve_body( $response ), true ),
		// );
	}
}
