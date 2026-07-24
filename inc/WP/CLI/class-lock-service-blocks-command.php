<?php
/**
 * WP CLI Command: Lock Service Blocks
 *
 * @package ChoctawNation
 */

namespace ChoctawNation\CNHSA_Federation\WP\CLI;

use WP_CLI;
use WP_Post;

/**
 * Locks service post blocks against moving and removal.
 */
class Lock_Service_Blocks_Command {
	/**
	 * Recursively locks every serialized block in one or more service posts.
	 *
	 * ## OPTIONS
	 *
	 * [<post-id>...]
	 * : One or more service post IDs.
	 *
	 * [--all]
	 * : Process every non-trashed service post.
	 *
	 * [--dry-run]
	 * : Report changes without updating post content.
	 *
	 * [--unlock]
	 * : Unlock blocks instead of locking them.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cno lock-service-blocks 123 --dry-run
	 *     wp cno lock-service-blocks 123 456
	 *     wp cno lock-service-blocks --all --dry-run
	 *     wp cno lock-service-blocks --all
	 *     wp cno lock-service-blocks 123 --unlock
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		$process_all = isset( $assoc_args['all'] );
		$dry_run     = isset( $assoc_args['dry-run'] );
		$unlock      = isset( $assoc_args['unlock'] );

		if ( $process_all && ! empty( $args ) ) {
			WP_CLI::error( 'Pass post IDs or --all, not both.' );
		}

		$post_ids = $process_all
			? $this->get_all_service_post_ids()
			: $this->sanitize_post_ids( $args );

		if ( empty( $post_ids ) ) {
			WP_CLI::error( 'No service posts were selected.' );
		}

		$updated_post_count  = 0;
		$changed_block_count = 0;

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post instanceof WP_Post ) {
				WP_CLI::warning(
					sprintf( 'Post %d does not exist; skipped.', $post_id )
				);
				continue;
			}

			if ( 'services' !== $post->post_type ) {
				WP_CLI::warning(
					sprintf(
						'Post %1$d is type "%2$s", not "services"; skipped.',
						$post_id,
						$post->post_type
					)
				);
				continue;
			}

			$blocks              = parse_blocks( $post->post_content );
			$post_changed_blocks = 0;
			$blocks              = $this->lock_blocks_recursively(
				$blocks,
				$post_changed_blocks,
				$unlock
			);

			if ( 0 === $post_changed_blocks ) {
				WP_CLI::log(
					sprintf(
						'Post %d: no unlocked blocks found.',
						$post_id
					)
				);
				continue;
			}

			++$updated_post_count;
			$changed_block_count += $post_changed_blocks;

			if ( $dry_run ) {
				WP_CLI::log(
					sprintf(
						'Post %1$d: would %2$s %3$d block(s).',
						$post_id,
						$unlock ? 'unlock' : 'lock',
						$post_changed_blocks
					)
				);
				continue;
			}

			$result = wp_update_post(
				wp_slash(
					array(
						'ID'           => $post_id,
						'post_content' => serialize_blocks( $blocks ),
					)
				),
				true
			);

			if ( is_wp_error( $result ) ) {
				WP_CLI::warning(
					sprintf(
						'Post %1$d could not be updated: %2$s',
						$post_id,
						$result->get_error_message()
					)
				);

				--$updated_post_count;
				$changed_block_count -= $post_changed_blocks;

				continue;
			}

			WP_CLI::log(
				sprintf(
					'Post %1$d: %2$s %3$d block(s).',
					$post_id,
					$unlock ? 'unlocked' : 'locked',
					$post_changed_blocks
				)
			);
		}

		$verb = $dry_run ? 'Would update' : 'Updated';

		WP_CLI::success(
			sprintf(
				'%1$s %2$d post(s) and %3$s %4$d block(s).',
				$verb,
				$updated_post_count,
				$unlock ? 'unlock' : 'lock',
				$changed_block_count
			)
		);
	}

	/**
	 * Adds move/remove locks to every named block in a parsed block tree.
	 *
	 * @param array $blocks        Parsed blocks.
	 * @param int   $changed_count Number of blocks changed.
	 * @param bool  $unlock         Whether to unlock blocks instead of locking them.
	 * @return array
	 */
	private function lock_blocks_recursively( $blocks, &$changed_count, $unlock ) {
		foreach ( $blocks as &$block ) {
			/*
			 * Non-block HTML is represented by a parsed entry whose
			 * blockName is null. It cannot receive block attributes.
			 */
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			$existing_lock = array();

			if (
				isset( $block['attrs']['lock'] ) &&
				is_array( $block['attrs']['lock'] )
			) {
				$existing_lock = $block['attrs']['lock'];
			}

			$new_lock = array_merge(
				$existing_lock,
				array(
					'move'   => ! $unlock,
					'remove' => ! $unlock,
				)
			);

			if ( $new_lock !== $existing_lock ) {
				$block['attrs']['lock'] = $new_lock;
				++$changed_count;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->lock_blocks_recursively(
					$block['innerBlocks'],
					$changed_count,
					$unlock
				);
			}
		}

		unset( $block );

		return $blocks;
	}

	/**
	 * Gets IDs for all non-trashed service posts.
	 *
	 * @return int[]
	 */
	private function get_all_service_post_ids() {
		$post_statuses = get_post_stati(
			array(
				'internal' => false,
			),
			'names'
		);

		return get_posts(
			array(
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'post_status'    => 'publish',
				'post_type'      => 'services',
				'posts_per_page' => -1,
			)
		);
	}

	/**
	 * Sanitizes positional post IDs.
	 *
	 * @param array $post_ids Raw post IDs.
	 * @return int[]
	 */
	private function sanitize_post_ids( $post_ids ) {
		$post_ids = array_map( 'absint', $post_ids );
		$post_ids = array_filter( $post_ids );

		return array_values( array_unique( $post_ids ) );
	}
}
