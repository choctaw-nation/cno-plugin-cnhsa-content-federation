<?php
/**
 * Class Location Payload Factory
 *
 * @package ChoctawNation\CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation\WP\Payload;

use WP_Post;
use WP_Error;

/**
 * Location Payload Factory Class
 * Responsible for constructing the payload for location posts to be sent to the external API.
 */
class Location_Payload_Factory extends Payload_Factory {
	/**
	 * Builds the payload for location posts.
	 *
	 * @param WP_Post $post The location post objects.
	 * @return array|WP_Error|null The payload array, a WP_Error on failure, or null if no payload is needed.
	 */
	public function create_payload( WP_Post $post ): array|WP_Error|null {
		if ( 'locations' !== $post->post_type && 'services' !== $post->post_type ) {
			return null;
		}
		/**
		 * Array of `locations` posts
		 *
		 * @var WP_Post[] $locations
		 */
		$locations = ( 'services' === $post->post_type ) ? get_field( 'location', $post->ID ) : array( $post );
		if ( empty( $locations ) ) {
			return null;
		}
		$location_data = array();
		foreach ( $locations as $location ) {
			$data     = array(
				'cno_location_id'         => $location->ID,
				'location_name'           => $location->post_title,
				'address'                 => get_field( 'address', $location->ID ),
				'city_state_zip'          => get_field( 'city_state_zip', $location->ID ),
				'phone_number'            => get_field( 'phone_number', $location->ID ),
				'additional_phone_number' => empty( get_field( 'additional_phone_number', $location->ID ) ) ? null : get_field( 'additional_phone_number', $location->ID ),
				'fax_number'              => empty( get_field( 'fax_number', $location->ID ) ) ? null : get_field( 'fax_number', $location->ID ),
			);
			$is_choctaw_location = 'external' !== get_field( 'choctaw_or_external_location', $location->ID );
			$location_type = get_field('type', $location->ID );
			if (!$is_choctaw_location) {
				$data['location_type'] = $location_type;
			} else {
				$data['location_type'] = 'Health Facility' !== $location_type ?'external': 'choctaw';
			}
			

			$cnhsa_id = get_post_meta( $location->ID, 'cnhsa_id', true );
			if ( ! empty( $cnhsa_id ) ) {
				$data['cnhsa_id'] = (int) $cnhsa_id;
			}
			$featured_image_id = get_field( 'photo', $location->ID );
			if ( $featured_image_id ) {
				$image_data = wp_get_attachment_image_src( $featured_image_id, 'full' );
				if ( $image_data ) {
					$data['featured_image'] = array(
						'src'          => $image_data[0],
						'cno_image_id' => $featured_image_id,
					);
				}
			}
			$location_data[] = $data;
		}
		return $location_data;
	}
}
