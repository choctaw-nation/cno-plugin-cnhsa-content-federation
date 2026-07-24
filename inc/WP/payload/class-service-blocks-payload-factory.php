<?php
/**
 * Blocks Payload Factory
 *
 * @package ChoctawNation
 */

namespace ChoctawNation\CNHSA_Federation\WP\Payload;

/**
 * Blocks Payload Factory Class
 */
class Service_Blocks_Payload_Factory {
	/**
	 * Builds the payload for service blocks.
	 *
	 * @param string $content The post content.
	 * @return array The payload array.
	 */
	public function create_payload( string $content ): ?array {
		if ( empty( $content ) ) {
			return null;
		}
		$blocks = parse_blocks( $content );
		if ( empty( $blocks ) ) {
			return null;
		}
		$filtered_blocks = $this->filter_empty_blocks( $blocks );
		$count_blocks    = count( $filtered_blocks );
		for ( $i = 0; $i < $count_blocks; $i++ ) {
			if ( ! empty( $filtered_blocks[ $i ]['innerBlocks'] ) ) {
				$filtered_blocks[ $i ]['innerBlocks'] = $this->filter_empty_blocks( $filtered_blocks[ $i ]['innerBlocks'] );
			}
		}
		return $filtered_blocks;
	}

	private function filter_empty_blocks( array $blocks ): array {
		return array_filter(
			$blocks,
			function ( $block ) {
				return ! empty( $block['blockName'] );
			}
		);
	}

	private function remove_unused_blocks() {
		$unused = array( 'core/featured-image' );
		if ( ! empty( $unused ) ) {
			$blocks = array_filter(
				$blocks,
				function ( $block ) use ( $unused ) {
					return ! in_array( $block['blockName'], $unused, true );
				}
			);
		}
	}
}
