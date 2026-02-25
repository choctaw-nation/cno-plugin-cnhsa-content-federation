<?php
/**
 * Service Federation Tests
 *
 * @package ChoctawNation\CNHSA_Federation\Tests
 */

namespace ChoctawNation\CNHSA_Federation\Tests;

use ChoctawNation\CNHSA_Federation\Transport\Http\Service_Publisher;
use ChoctawNation\CNHSA_Federation\WP\ID_Resolver;
use ChoctawNation\CNHSA_Federation\WP\Notifier;
use WP_UnitTestCase;

/**
 * Class Test_Service_Federation
 */
class Test_Service_Federation extends WP_UnitTestCase {
	/**
	 * Set up test configs
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		update_option(
			'cnhsa_federation_options',
			array(
				'environments' => array( 'local' ),
				'credentials'  => array(
					'local' => array(
						'username'     => 'test-user',
						'app_password' => 'test-password',
					),
				),
			)
		);
	}

	/**
	 * Clean up options after tests
	 */
	public static function tear_down_after_class(): void {
		delete_option( 'cnhsa_federation_options' );
		parent::tear_down_after_class();
	}

	/**
	 * Test that the service federation class can be instantiated and has the expected methods.
	 */
	public function test_insert_service_updates_post_meta_on_success() {
		$mock_service_post = self::factory()->post->create_and_get( array( 'post_type' => 'cnhsa_service' ) );
		$mock_id_resolver  = $this->getMockBuilder( ID_Resolver::class )
			->disableOriginalConstructor()
			->getMock();
		$mock_notifier     = $this->getMockBuilder( Notifier::class )
			->disableOriginalConstructor()
			->getMock();
		$publisher         = new Service_Publisher( 'local', $mock_id_resolver, $mock_notifier );
		// Mock the HTTP response that `wp_remote_post` will receive.
		HTTP_Requests::successful_request( array( 'data' => array( 'id' => 123 ) ), 201 );

		$publisher->publish_content( $mock_service_post );

		// Remove the mock so it doesn't affect other tests.
		HTTP_Requests::clear_filters();

		$this->assertSame( 123, (int) get_post_meta( $mock_service_post->ID, 'cnhsa_services_id', true ) );
	}

	/**
	 * Test that the service federation class sends an email on failure.
	 */
	public function test_insert_service_sends_mail_on_failure() {
		$mock_service_post = self::factory()->post->create_and_get( array( 'post_type' => 'cnhsa_service' ) );
		$mock_id_resolver  = $this->getMockBuilder( ID_Resolver::class )
			->disableOriginalConstructor()
			->getMock();
		$mock_notifier     = $this->getMockBuilder( Notifier::class )
			->disableOriginalConstructor()
			->getMock();
		$publisher         = new Service_Publisher( 'local', $mock_id_resolver, $mock_notifier );
		// Mock the HTTP response that `wp_remote_post` will receive.
		HTTP_Requests::failed_request();

		$email_did_trigger = false;
		add_filter(
			'pre_wp_mail',
			function () use ( &$email_did_trigger ) {
				$email_did_trigger = true;
				return false; // prevent email from sending
			}
		);

		$publisher->publish_content( $mock_service_post );

		// Remove the mock so it doesn't affect other tests.
		HTTP_Requests::clear_filters();
		remove_all_filters( 'pre_wp_mail' );
		$this->assertTrue( $email_did_trigger );
	}
}
