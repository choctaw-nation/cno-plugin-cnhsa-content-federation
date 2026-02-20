<?php
/**
 * CNHSA Federation Controller Tests
 *
 * @package ChoctawNation\Tests
 */

namespace ChoctawNation\Tests\Theme\CNHSA_API;

use ChoctawNation\CNHSA_Federation\Controller;
use ChoctawNation\CNHSA_Federation\Model;
use WP_Post;
use WP_Error;
use WP_UnitTestCase;

/**
 * Class Test_Controller
 */
class Test_Controller extends WP_UnitTestCase {
	/**
	 * Set up the test environment before each test.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		// simulate existing categories, accounting for cat id 1 == 'uncategorized'
		self::factory()->term->create_many( 10, array( 'taxonomy' => 'category' ) );
		// ensure category with term id 12 exists for testing
		self::factory()->term->create(
			array(
				'name'     => 'health',
				'taxonomy' => 'category',
			)
		);
	}

	/**
	 * Nothing should be scheduled if non-production environment
	 *
	 * @param string|null  $env The environment to test (e.g., 'production', 'staging', null).
	 * @param bool|null    $autosave Whether to simulate an autosave condition.
	 * @param WP_Post|null $post The post object to use for testing.
	 * @param bool|null    $update Whether to simulate an update or a new post.
	 * @dataProvider data_provider_should_return_conditions
	 * @return void
	 */
	public function test_schedule_update_returns_null_when_should_return_is_true( $env, $autosave, $post, $update ): void {
		$model      = $this->getMockBuilder( Model::class )
		->onlyMethods( array( 'update_service', 'create_service', 'get_cnhsa_services_id' ) )
		->setConstructorArgs( array( $env ) )
		->enableOriginalConstructor()
		->getMock();
		$controller = new Controller( $model, $autosave );
		$result     = $controller->schedule_update( 10, $post, $update );
		$this->assertNull( $result, 'Expected schedule_update to return null when should_return is true' );
	}

	/**
	 * Data provider for testing conditions that should cause schedule_update to return early.
	 *
	 * @return array
	 */
	public function data_provider_should_return_conditions() {
		return array(
			'doing autosave'                               => array(
				'production',
				true,
				// create valid post to ensure autosave condition is the reason for returning null
				self::factory()->post->create_and_get(
					array(
						'post_status'   => 'publish',
						'post_category' => array( 12 ),
					)
				),
				true,
			),
			'post status is not publish, draft or pending' => array(
				'production',
				null,
				self::factory()->post->create_and_get(
					array(
						'post_status' => 'private',
					)
				),
				true,
			),
			'category does not have term id 12'            => array(
				'production',
				null,
				self::factory()->post->create_and_get(
					array(
						'post_status'   => 'publish',
						'post_category' => array( 999 ), // Assuming 999 is not a valid category ID.
					)
				),
				true,
			),
		);
	}

	/**
	 * Ensure a successful schedule invocation calls the scheduler and does not send mail.
	 */
	public function test_schedule_update_success_schedules_cron() {
		// configure model mock to indicate existing CNHSA service id
		$model      = $this->getMockBuilder( Model::class )
		->onlyMethods( array( 'update_service', 'create_service', 'get_cnhsa_services_id' ) )
		->enableOriginalConstructor()
		->setConstructorArgs( array( 'local' ) )
		->getMock();
		$controller = new Controller( $model );
		$post       = self::factory()->post->create_and_get(
			array(
				'post_status'   => 'publish',
				'post_category' => array( 12 ),
			)
		);
		// wp_set_post_terms( $post->ID, array( 12 ), 'category' );
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'object_ids' => $post->ID,
				'fields'     => 'ids',
			)
		);
		$model->expects( $this->once() )
		->method( 'get_cnhsa_services_id' )
		->with( $post )
		->willReturn( 123 ); // Simulate existing service ID to trigger update flow.
		$result = $controller->schedule_update( 10, $post, true );
		$this->assertTrue( $result, 'Expected schedule_update to return true when scheduling is successful' );
	}

	/**
	 * Ensure a scheduling error results in a WP_Error return and triggers an email notification.
	 */
	public function test_schedule_update_scheduling_error_sends_mail() {
		// make updating the 'cron' option a no-op so scheduling returns false
		$filter = function ( $new_value, $option ) {
			if ( 'cron' === $option ) {
				return get_option( 'cron' ); // return existing value -> update_option will return false
			}
			return $new_value;
		};
		add_filter( 'pre_update_option', $filter, 10, 2 );
		$email_did_trigger = false;
		add_filter(
			'pre_wp_mail',
			function () use ( &$email_did_trigger ) {
				$email_did_trigger = true;
				return false; // prevent email from sending
			}
		);
		// configure model mock to indicate existing CNHSA service id
		$model      = $this->getMockBuilder( Model::class )
		->onlyMethods( array( 'update_service', 'create_service', 'get_cnhsa_services_id' ) )
		->enableOriginalConstructor()
		->setConstructorArgs( array( 'local' ) )
		->getMock();
		$controller = new Controller( $model );
		$post       = self::factory()->post->create_and_get(
			array(
				'post_status'   => 'publish',
				'post_category' => array( 12 ),
			)
		);
		$model->expects( $this->once() )
		->method( 'get_cnhsa_services_id' )
		->with( $post )
		->willReturn( 123 ); // Simulate existing service ID to trigger update flow.
		$result = $controller->schedule_update( 10, $post, true );
		$this->assertInstanceOf( WP_Error::class, $result, 'Expected schedule_update to return WP_Error on scheduling failure.' );
		$this->assertTrue( $email_did_trigger, 'Expected an email to be triggered on scheduling failure.' );
		remove_all_filters( 'pre_update_option' );
		remove_all_filters( 'pre_wp_mail' );
	}

	/**
	 * Ensure model callbacks are registered by Controller::wire_callbacks().
	 *
	 * @dataProvider data_provider_model_hooks
	 *
	 * @param string $hook    The hook name to check.
	 * @param string $method  The model method expected to be registered.
	 * @param string $message Assertion message.
	 */
	public function test_wire_callbacks_registers_model_hooks( string $hook, string $method, string $message ) {
		$model      = $this->getMockBuilder( Model::class )
		->onlyMethods(
			array(
				'update_service',
				'create_service',
				'update_health_location',
				'get_cnhsa_services_id',
			)
		)
		->disableOriginalConstructor()
		->getMock();
		$controller = new Controller( $model );
		$controller->wire_callbacks();

		$this->assertNotFalse(
			has_action( $hook, array( $model, $method ) ),
			$message
		);
	}

	/**
	 * Data provider for model hook expectations.
	 *
	 * @return array
	 */
	public function data_provider_model_hooks() {
		return array(
			array(
				'cnhsa_federation_update_services',
				'update_service',
				'Expected update_service to be hooked to cnhsa_federation_update_services',
			),
			array(
				'cnhsa_federation_create_services',
				'create_service',
				'Expected create_service to be hooked to cnhsa_federation_create_services',
			),
			array(
				'cnhsa_federation_update_health_location',
				'update_health_location',
				'Expected update_health_location to be hooked to cnhsa_federation_update_health_location',
			),
		);
	}

	/**
	 * Ensure controller callbacks are registered by Controller::wire_callbacks().
	 *
	 * @dataProvider data_provider_controller_hooks
	 *
	 * @param string $hook    The hook name to check.
	 * @param string $method  The controller method expected to be registered.
	 * @param string $message Assertion message.
	 */
	public function test_wire_callbacks_registers_controller_hooks( string $hook, string $method, string $message ) {
		$model      = $this->getMockBuilder( Model::class )
		->onlyMethods(
			array(
				'update_service',
				'create_service',
				'update_health_location',
				'get_cnhsa_services_id',
			)
		)
		->disableOriginalConstructor()
		->getMock();
		$controller = new Controller( $model );
		$controller->wire_callbacks();

		$this->assertNotFalse(
			has_action( $hook, array( $controller, $method ) ),
			$message
		);
	}

	/**
	 * Data provider for controller hook expectations.
	 *
	 * @return array
	 */
	public function data_provider_controller_hooks() {
		return array(
			array(
				'save_post_services',
				'schedule_update',
				'Expected schedule_update to be hooked to save_post_services',
			),
			array(
				'save_post_locations',
				'schedule_location_update',
				'Expected schedule_location_update to be hooked to save_post_locations',
			),
		);
	}
}
