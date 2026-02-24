<?php
/**
 * Class Location Payload Builder
 *
 * @package ChoctawNation\CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation\WP\Payload;

use WP_Post;

/**
 * Location Payload Builder Class
 * Responsible for constructing the payload for location posts to be sent to the external API.
 */
class Location_Payload_Builder {
	/**
	 * Builds the payload for location posts.
	 *
	 * @param WP_Post[] $locations The location post objects.
	 * @return array The payload array.
	 */
	public function build_payload( array $locations ): array {
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
}
