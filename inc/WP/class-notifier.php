<?php
/**
 * Notifier Class
 *
 * @package ChoctawNation\CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation\WP;

/**
 * Class Notifier
 *
 * Handles sending notification emails to specified addresses.
 */
class Notifier {
	/**
	 * Array of email addresses to notify.
	 *
	 * @var string[] $emails
	 */
	public array $emails;

	/**
	 * Constructor
	 *
	 * @param string|string[] $emails A single email address or an array of email addresses to notify.
	 */
	public function __construct( $emails = '' ) {
		$emails       = is_array( $emails ) ? $emails : array( $emails );
		$emails       = array_filter( $emails, 'is_email' );
		$this->emails = array_unique( array( ...$emails, get_option( 'admin_email' ) ) );
	}

	/**
	 * Send a notification email to the configured email addresses.
	 *
	 * @param string $subject The subject of the email.
	 * @param string $message The body of the email.
	 */
	public function notify( $subject, $message ) {
		wp_mail( $this->emails, esc_textarea( $subject ), esc_html( $message ) );
	}
}
