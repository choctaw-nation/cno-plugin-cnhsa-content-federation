<?php
/**
 * Class Service Payload Factory
 *
 * @package ChoctawNation\CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation\WP\Payload;

use WP_Error;
use WP_Post;

/**
 * Service Payload Factory Class
 * Responsible for constructing the payload for service posts to be sent to the external API.
 */
class Service_Payload_Factory extends Payload_Factory {
	/**
	 * Builds the payload for a service post.
	 *
	 * @param WP_Post $data The service post object.
	 * @return array|WP_Error|null The payload array, a WP_Error on failure, or null if no payload is needed.
	 */
	public function create_payload( WP_Post $data ): array|WP_Error|null {
		$content   = $this->build_acf_field_data( $data->ID );
		$rest_data = array(
			'title'     => $data->post_title,
			'status'    => $data->post_status,
			'slug'      => $data->post_name,
			'excerpt'   => get_field(
				'archive_content',
				$data->ID
			),
			'post_data' => $content,
		);
		$locations = get_field( 'location', $data->ID );
		if ( ! empty( $locations ) ) {
			$rest_data['location_data'] = $locations;
		}

		$additional_categories = $this->add_additional_categories( $data->ID );
		if ( ! empty( $additional_categories ) ) {
			$rest_data['additional_categories'] = $additional_categories;
		}

		return $rest_data;
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
