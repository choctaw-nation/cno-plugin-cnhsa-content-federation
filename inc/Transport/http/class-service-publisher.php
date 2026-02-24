<?php
/**
 * Class: Service Publisher
 * Handles data operations between the CNO & CNHSA themes.
 *
 * @package ChoctawNation
 * @subpackage CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation\Transport\Http;

use WP_Post;

/**
 * Class Service_Publisher
 * Manages the data operations for the CNHSA Federation.
 */
class Service_Publisher extends Abstract_Publisher {
	public function get_cnhsa_id( WP_Post $post ): int {
		return $this->id_resolver->find_cnhsa_id( 'services', $post );
	}

	public function publish_content( WP_Post $post ): void {
		$post_id       = $this->get_cnhsa_id( $post );
		$url           = $post_id ? "{$this->base_url}/service/{$post_id}" : "{$this->base_url}/service";
		$response      = wp_remote_post(
			$url,
			array(
				'method'  => 'POST',
				'body'    => $this->prepare_data( $post ),
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Basic ' . $this->get_auth(),
				),
			)
		);
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( is_wp_error( $response ) || 201 !== $response_code ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			wp_mail(
				'kroelke@choctawnation.com',
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
	 * Prepares REST Data for the CNHSA Federation API.
	 *
	 * @param WP_Post $data The post object.
	 * @return string The JSON encoded REST data.
	 */
	public function prepare_data( WP_Post $data ): string {
		$content   = $this->build_acf_field_data( $data->ID );
		$location  = $this->build_location_payload( $data->ID );
		$rest_data = array(
			'title'         => $data->post_title,
			'status'        => $data->post_status,
			'slug'          => $data->post_name,
			'excerpt'       => get_field(
				'archive_content',
				$data->ID
			),
			'post_data'     => $content,
			'location_data' => $location,
		);

		$additional_categories = $this->add_additional_categories( $data->ID );
		if ( ! empty( $additional_categories ) ) {
			$rest_data['additional_categories'] = $additional_categories;
		}

		return wp_json_encode(
			$rest_data
		);
	}

	/**
	 * Builds the ACF field data for the CNHSA Federation API.
	 *
	 * @param int $id The post ID.
	 * @return array The ACF field data.
	 */
	private function build_acf_field_data( int $id ): array {
		$content = array();
		$fields  = array(
			'cnhsa_eligibility_guidelines',
			'eligibility_requirements',
			'how_to_apply',
			'apply_online',
			'online_application_url',
			'application_address',
			'inside_or_outside_reservation',
			'is_application_always_open',
			'application_period_start',
			'application_period_end',
			'application_period_open',
			'additional_information',
			'faq_title',
			'faq_in_modal',
			'faq',
			'form_title',
			'form',
			'related_media',
			'contact_information',
		);
		foreach ( $fields as $field ) {
			if ( 'related_media' === $field ) {
				$media             = get_field( $field, $id );
				$content[ $field ] = $this->handle_related_media( $media );
				continue;
			}
			$content[ $field ] = get_field( $field, $id );
		}
		return $content;
	}

	/**
	 * Sets “related media” ACF data appropriately for the CNHSA Federation API.
	 *
	 * @param array|null $media The related media field data.
	 * @return array The processed related media data.
	 */
	private function handle_related_media( $media ): array {
		if ( empty( $media ) ) {
			return array();
		}
		$media_data = array_map(
			function ( $data ) {
				$parsed  = array();
				$is_link = false !== strpos( $data['file_or_link'], '_link' );
				if ( $is_link ) {
					$parsed['internal_link'] = null;
					$parsed['external_link'] = $data['internal_link'];
				} else {
					$parsed['internal_link'] = null;
					$parsed['external_link'] = $data['pdf'];
				}
				$parsed['file_or_link'] = 'external_link';
				return array( ...$data, ...$parsed );
			},
			$media
		);
		return $media_data;
	}

	/**
	 * Builds the location payload for the CNHSA Federation API.
	 *
	 * @param int $id The post ID.
	 * @return array|null The location payload or null if not found.
	 */
	private function build_location_payload( int $id ): ?array {
		/**
		 * Array of location posts
		 *
		 * @var WP_Post[] $location
		 */
		$locations = get_field( 'location', $id );
		if ( empty( $locations ) ) {
			return null;
		}
		$location_data = array();
		foreach ( $locations as $location ) {
			$data = array(
				'cno_location_id'         => $location->ID,
				'cnhsa_id'                => empty( get_field( 'cnhsa_id', $location->ID ) ) ? null : (int) get_field( 'cnhsa_id', $location->ID ),
				'address'                 => get_field( 'address', $location->ID ),
				'city_state_zip'          => get_field( 'city_state_zip', $location->ID ),
				'phone_number'            => get_field( 'phone_number', $location->ID ),
				'additional_phone_number' => empty( get_field( 'additional_phone_number', $location->ID ) ) ? null : get_field( 'additional_phone_number', $location->ID ),
				'fax_number'              => empty( get_field( 'fax_number', $location->ID ) ) ? null : get_field( 'fax_number', $location->ID ),
			);
			if ( null === $data['cnhsa_id'] ) {
				$data['location_name'] = $location->post_title;
			}
			$location_data[] = $data;
		}
		return $location_data;
	}


	/**
	 * Add additional categories excluding 'Health'.
	 *
	 * @param int $id The post ID.
	 * @return array|null The additional categories or null if none found.
	 */
	private function add_additional_categories( int $id ): ?array {
		$categories = get_categories(
			array(
				'object_ids' => $id,
			)
		);
		if ( empty( $categories ) ) {
			return null;
		}
		return array_filter(
			wp_list_pluck( $categories, 'name' ),
			fn( $cat ) => 'Health' !== $cat
		);
	}
}
