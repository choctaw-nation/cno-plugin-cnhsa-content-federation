<?php
/**
 * ID Resolver Class
 * Handles matching of data between the CNO & CNHSA sites.
 *
 * @package ChoctawNation
 * @subpackage CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation\WP;

use Exception;
use WP_Post;

/**
 * Class ID_Resolver
 */
class ID_Resolver {
	/**
	 * Finds the CNHSA ID for a given post type and post ID.
	 *
	 * @param 'services'|'location' $post_type The post type (slug) to search for (e.g., 'services').
	 * @param WP_Post               $post      The local post object to find the corresponding CNHSA ID for.
	 * @param string                $base_url  The base URL of the CNHSA site to query.
	 * @return int The CNHSA ID if found, or 0 if not found.
	 */
	public function find_cnhsa_id( string $post_type, WP_Post $post, string $base_url ): int {
		$cnhsa_id = (int) get_post_meta( $post->ID, "cnhsa_{$post_type}_id", true );
		if ( 0 !== $cnhsa_id ) {
			return $cnhsa_id;
		}
		if ( 'services' === $post_type ) {
			$cnhsa_id = $this->find_cnhsa_service_id( $base_url, $post->post_name );
		} elseif ( 'location' === $post_type ) {
			$cnhsa_id = $this->find_cnhsa_location_id( $base_url, $post->post_name );
		}
		return $cnhsa_id;
	}

	/**
	 * Resolves the CNHSA ID for a given post type and post ID by querying the CNHSA API.
	 *
	 * @param string $base_url The base URL of the CNHSA site to query.
	 * @param string $slug The slug of the local post to find the corresponding CNHSA ID for.
	 * @return int The CNHSA ID if found, or 0 if not found.
	 * @throws Exception If the API request fails or returns an invalid response.
	 */
	public function find_cnhsa_service_id( string $base_url, string $slug ): int {
		$paged = 1;
		$found = array();
		do {
			++$paged;
			$response = wp_remote_get(
				"{$base_url}/wp/v2/services?_fields=id,title,slug&per_page=100&page={$paged}",
			);
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				$message = is_wp_error( $response ) ? $response->get_error_message() : 'Invalid response code';
				throw new Exception( esc_textarea( $message ) );
			}
			$pages = (int) wp_remote_retrieve_header( $response, 'X-WP-TotalPages' );
			$body  = wp_remote_retrieve_body( $response );
			$data  = json_decode( $body, true );
			if ( empty( $data ) || ! is_array( $data ) ) {
				return 0;
			}
			$found = array_filter(
				$data,
				function ( $item ) use ( $slug ) {
					return isset( $item['slug'] ) && $item['slug'] === $slug;
				}
			);
		} while ( $paged < $pages && empty( $found ) );
		if ( empty( $found ) ) {
			return 0;
		}
		return (int) $found[0]['id'];
	}

	/**
	 * Resolves the CNHSA ID for a given post type and post ID by querying the CNHSA API.
	 *
	 * @param string $base_url The base URL of the CNHSA site to query.
	 * @param string $slug      The slug of the local post to find the corresponding CNHSA ID for.
	 * @return int The CNHSA ID if found, or 0 if not found.
	 * @throws Exception If the API request fails or returns an invalid response.
	 */
	public function find_cnhsa_location_id( string $base_url, string $slug ): int {
		$found          = array();
		$post_type_keys = array( 'clinic', 'additional-facility' );

		foreach ( $post_type_keys as $post_type ) {
			$paged = 1;
			do {
				$response = wp_remote_get(
					"{$base_url}/wp/v2/{$post_type}?_fields=id,title,slug&per_page=100&page={$paged}",
				);
				if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
					$message = is_wp_error( $response ) ? $response->get_error_message() : 'Invalid response code';
					throw new Exception( esc_textarea( $message ) );
				}
				$pages = (int) wp_remote_retrieve_header( $response, 'X-WP-TotalPages' );
				$body  = wp_remote_retrieve_body( $response );
				$data  = json_decode( $body, true );
				if ( empty( $data ) || ! is_array( $data ) || ! isset( $data[0] ) ) {
					++$paged;
					continue;

				}
				$found = array_filter(
					$data,
					function ( $item ) use ( $slug ) {
						return isset( $item['slug'] ) && $item['slug'] === $slug;
					}
				);
				if ( ! empty( $found ) ) {
					break 2; // Break out of both loops if a match is found
				}
				++$paged;
			} while ( empty( $found ) && $paged <= $pages );
		}
		if ( empty( $found ) ) {
			return 0;
		}
		return (int) $found[0]['id'];
	}
}
