<?php
/**
 * Notifier Test Class
 *
 * @package Choctawnation\CNHSA_Federation\Tests
 */

namespace ChoctawNation\CNHSA_Federation\Tests;

use WP_UnitTestCase;

/**
 * Class Test_Notifier
 */
class Test_Notifier extends WP_UnitTestCase {
	/**
	 * Set up the test environment.
	 */
	public function set_up(): void {
		parent::set_up();
		update_option( 'admin_email', 'admin@example.com' );
	}

	/**
	 * Test that the Notifier class correctly filters and stores valid email addresses.
	 */
	public function test_notifier_sets_and_validates_emails_correctly() {
		$emails   = array( 'test@example.com', 'test@example.com', 'test at example dot com' );
		$notifier = new \ChoctawNation\CNHSA_Federation\WP\Notifier( $emails );
		$this->assertCount( 2, $notifier->emails );
	}

	/**
	 * Test that the Notifier class correctly handles a single email string input.
	 */
	public function test_notifier_handles_single_email_string() {
		$email    = 'single@example.com';
		$notifier = new \ChoctawNation\CNHSA_Federation\WP\Notifier( $email );
		$this->assertCount( 2, $notifier->emails );
	}

	/**
	 * Test that the Notifier class correctly defaults to the admin email when no input is provided.
	 */
	public function test_notifier_defaults_to_admin_email() {
		$notifier = new \ChoctawNation\CNHSA_Federation\WP\Notifier();
		$this->assertCount( 1, $notifier->emails );
		$this->assertEquals( 'admin@example.com', $notifier->emails[0] );
	}

	/**
	 * Test that the Notifier class correctly escapes email message content to prevent XSS vulnerabilities.
	 */
	public function test_notifier_escapes_email_message() {
		$message  = '<script>alert("test")</script>';
		$notifier = new \ChoctawNation\CNHSA_Federation\WP\Notifier();
		add_filter(
			'pre_wp_mail',
			function ( $return, $args ) use ( $message ) {
				$this->assertEquals( esc_html( $message ), $args['message'] );
				return $args;
			},
			10,
			2
		);
		$notifier->notify( 'Test Subject', $message );
		remove_all_filters( 'pre_wp_mail' );
	}
}
