<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Payload Factory Abstract Class
 *
 * @package ChoctawNation\CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation\WP\Payload;

use WP_Error;
use WP_Post;

/**
 * Abstract Payload Factory Class
 * Defines the interface for building payloads for different post types to be sent to the external API.
 */
abstract class Payload_Factory {
	/**
	 * Builds the payload for a given post.
	 *
	 * @param WP_Post $data The post object to build the payload from.
	 * @return array|WP_Error|null The payload array, a WP_Error on failure, or null if no payload is needed.
	 */
	abstract public function create_payload( WP_Post $data ): array|WP_Error|null;
}
