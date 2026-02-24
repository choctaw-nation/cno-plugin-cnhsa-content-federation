<?php
/**
 * Class: Scheduler
 * Manages the interaction between the model and the view.
 *
 * @package ChoctawNation
 * @subpackage CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation\WP;

use WP_Post;

/**
 * Scheduler Class
 */
class Scheduler {
	/**
	 * Cron keys for scheduling updates and creations.
	 *
	 * @var array $cron_keys
	 */
	public array $cron_keys;

	/**
	 * Notification emails for error reporting.
	 *
	 * @var string[] $notification_emails
	 */
	private array $notification_emails;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->cron_keys           = array(
			'services'  => array(
				'update' => 'cnhsa_federation_update_services',
				'create' => 'cnhsa_federation_create_services',
			),
			'locations' => array(
				'update' => 'cnhsa_federation_update_health_location',
				'create' => 'cnhsa_federation_create_health_location',
			),
		);
		$this->notification_emails = array_unique( array( get_option( 'admin_email' ), 'kroelke@choctawnation.com', 'bperkins@choctawnation.com' ), SORT_STRING );
	}

	/**
	 * Schedule an update for the specified post.
	 *
	 * @param int     $post_id The ID of the post to update.
	 * @param WP_Post $post The post object.
	 * @param bool    $update Whether this is an update or a new post.
	 * @return ?bool Bool on schedule or null if the scheduling was skipped due to conditions not being met.
	 */
	public function schedule_services_update( int $post_id, WP_Post $post, bool $update ): ?bool {
		if ( $this->should_skip( $post, $update ) ) {
			return null;
		}
		if ( false === $update ) { // new service post, won't exist on CNHSA, so schedule create.
			return $this->schedule_single_event( $this->cron_keys['services']['create'], array( 0, $post ) );
		}
		// $cnhsa_services_id = $this->model->get_cnhsa_services_id( $post );
		$hook = $this->cron_keys['services']['update'];
		return $this->schedule_single_event( $hook, array( 0, $post ) );
	}

	/**
	 * Schedule an update for the specified health location post.
	 *
	 * @param int     $post_id The ID of the post to update.
	 * @param WP_Post $post The post object.
	 * @param bool    $update Whether this is an update or a new post.
	 */
	public function schedule_locations_update( int $post_id, WP_Post $post, bool $update ): ?bool {
		if ( $this->should_skip( $post, $update ) ) {
			return null;
		}
		// $cnhsa_locations_id = $this->model->get_cnhsa_locations_id( $post );
		// $hook               = 0 === $cnhsa_locations_id ? $this->cron_keys['locations']['create'] : $this->cron_keys['locations']['update'];
		return $this->schedule_single_event( $this->cron_keys['locations']['update'], array( 0, $post ) );
	}

	/**
	 * Schedule a single event with the given hook and arguments, avoiding duplicates.
	 * Sends an email if scheduling fails.
	 *
	 * @param string $hook The name of the hook to schedule.
	 * @param array  $args The arguments to pass to the hook when it is executed.
	 * @return bool True if the event was scheduled successfully, false if it failed or was already scheduled.
	 */
	private function schedule_single_event( string $hook, array $args ): bool {
		// avoid duplicate schedules
		$next = wp_next_scheduled( $hook, $args );
		if ( $next ) {
			return true; // already scheduled
		}

		$timestamp = time() + 5;
		$ok        = wp_schedule_single_event( $timestamp, $hook, $args );
		if ( ! $ok ) {
			$this->notifier->notify( 'Error scheduling cron', sprintf( 'Failed to schedule %s with args: %s', $hook, wp_json_encode( $args ) ) );
		}
		return $ok;
	}

	/**
	 * Bail out if conditions aren't met. Conditions include:
	 * - If the post is being autosaved.
	 * - If the post status is not 'publish', 'draft', or 'pending'
	 * - If the post does not have the category with ID 12 (which is the "Health" category).
	 *
	 * @param WP_Post $post The post object.
	 * @param bool    $update Whether this is an update or a new post.
	 * @return bool True if the post should be returned, false otherwise.
	 */
	private function should_skip( WP_Post $post, bool $update ): bool {
		$should_skip = false;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			// Quick early return if this is an autosave.
			return true;
		}

		if ( $update && ! in_array( $post->post_status, array( 'publish', 'draft', 'pending' ), true ) ) {
			$should_skip = true;
		}
		if ( 'services' === $post->post_type && ! has_term( 12, 'category', $post ) ) {
			$should_skip = true;
		}
		return $should_skip;
	}
}
