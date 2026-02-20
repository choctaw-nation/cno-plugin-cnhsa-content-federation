<?php
/**
 * Class: Controller
 * Manages the interaction between the model and the view.
 *
 * @package ChoctawNation
 * @subpackage CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation;

use WP_Error;
use WP_Post;

/**
 * Controller Class
 * Handles the scheduling of updates and creations for the CNHSA Federation.
 */
class Controller {
	/**
	 * Instance of the Model class.
	 *
	 * @var Model $model;
	 */
	private Model $model;

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
	 * Whether the current request is an autosave.
	 *
	 * @var bool $is_doing_autosave
	 */
	private bool $is_doing_autosave;

	/**
	 * Constructor
	 *
	 * @param Model     $model An instance of the Model class to handle data operations.
	 * @param bool|null $doing_autosave Optional. Whether the current request is an autosave. Defaults to null.
	 */
	public function __construct( Model $model, ?bool $doing_autosave = null ) {
		$this->is_doing_autosave   = $doing_autosave ?? ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE );
		$this->cron_keys           = array(
			'update'                 => 'cnhsa_federation_update_services',
			'create'                 => 'cnhsa_federation_create_services',
			'update_health_location' => 'cnhsa_federation_update_health_location',
		);
		$this->model               = $model;
		$this->notification_emails = array_unique( array( get_option( 'admin_email' ), 'kroelke@choctawnation.com', 'bperkins@choctawnation.com' ), SORT_STRING );
	}

	/**
	 * Add callbacks for the various actions and filters.
	 */
	public function wire_callbacks(): void {
		$callbacks = array(
			$this->cron_keys['update']                 => array( $this->model, 'update_service' ),
			$this->cron_keys['create']                 => array( $this->model, 'create_service' ),
			$this->cron_keys['update_health_location'] => array( $this->model, 'update_health_location' ),
			'save_post_services'                       => array( $this, 'schedule_update' ),
			'save_post_locations'                      => array( $this, 'schedule_location_update' ),
		);
		foreach ( $callbacks as $hook => $callback ) {
			add_action( $hook, $callback, 10, 3 );
		}
	}

	/**
	 * Schedule an update for the specified post.
	 *
	 * @param int     $post_id The ID of the post to update.
	 * @param WP_Post $post The post object.
	 * @param bool    $update Whether this is an update or a new post.
	 * @return null|true|WP_Error True on schedule, a WP_Error on failure, or null if the scheduling was skipped due to conditions not being met.
	 */
	public function schedule_update( int $post_id, WP_Post $post, bool $update ): null|true|WP_Error {
		if ( $this->should_return( $post, $update ) ) {
			return null;
		}
		$cnhsa_services_id = $this->model->get_cnhsa_services_id( $post );
		$action_timestamp  = time() + 5; // Schedule to run in 5 seconds.
		$action_args       = array( $cnhsa_services_id, $post );
		$hook              = 0 === $cnhsa_services_id ? $this->cron_keys['create'] : $this->cron_keys['update'];
		/**
		 * Disabled for production.
		 * `do_action` should only be used for testing, but it causes synchronous updates, which will increase the amount of time needed to update a post.
		 * do_action( $hook, ...$action_args );
		 */
		$is_scheduled = wp_schedule_single_event( $action_timestamp, $hook, $action_args, true );
		if ( is_wp_error( $is_scheduled ) ) {
			$this->send_email( 'Error scheduling service update cron', $is_scheduled->get_error_message() );
		}
		return $is_scheduled;
	}

	/**
	 * Send a notification email to the configured notification emails.
	 *
	 * @param string $subject The subject of the email.
	 * @param string $message The message body of the email.
	 */
	public function send_email( string $subject, string $message ): void {
		wp_mail( $this->notification_emails, $subject, $message );
	}

	/**
	 * Schedule an update for the specified health location post.
	 *
	 * @param int     $post_id The ID of the post to update.
	 * @param WP_Post $post The post object.
	 * @param bool    $update Whether this is an update or a new post.
	 */
	public function schedule_location_update( int $post_id, WP_Post $post, bool $update ): void {
		if ( $this->should_return( $post, $update ) ) {
			return;
		}
		$action_timestamp = time() + 5; // Schedule to run in 5 seconds.
		$hook             = $this->cron_keys['update_health_location'];
		$action_args      = array( $cnhsa_location_id, $post );
		/**
		 * Disabled for production.
		 * `do_action` should only be used for testing, but it causes synchronous updates, which will increase the amount of time needed to update a post.
		 * do_action( $hook, ...$action_args );
		 */
		$is_scheduled = wp_schedule_single_event( $action_timestamp, $hook, $action_args, true );
		if ( is_wp_error( $is_scheduled ) ) {
			$this->send_email( 'Error scheduling location update cron', $is_scheduled->get_error_message() );
		}
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
	private function should_return( WP_Post $post, bool $update ): bool {
		$should_return = false;
		if ( $this->is_doing_autosave ) {
			// Quick early return if this is an autosave.
			return true;
		}

		if ( $update && ! in_array( $post->post_status, array( 'publish', 'draft', 'pending' ), true ) ) {
			$should_return = true;
		}
		if ( ! has_term( 12, 'category', $post ) ) {
			$should_return = true;
		}
		return $should_return;
	}
}
