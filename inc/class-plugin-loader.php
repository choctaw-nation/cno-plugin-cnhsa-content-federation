<?php
/**
 * Plugin Loader
 *
 * @package CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation;

use ChoctawNation\CNHSA_Federation\WP\AdminScreen\Admin_Screen;
use ChoctawNation\CNHSA_Federation\WP\Cron_Handler;
use ChoctawNation\CNHSA_Federation\WP\ID_Resolver;
use ChoctawNation\CNHSA_Federation\WP\Notifier;
use ChoctawNation\CNHSA_Federation\WP\Scheduler;

/**
 * Class Plugin_Loader
 */
class Plugin_Loader {
	/**
	 * The option key for storing plugin settings in the database.
	 *
	 * @var string $option_key
	 */
	private const OPTION_KEY = 'cnhsa_federation_options';

	/**
	 * The transient key for storing the local URL in the database.
	 *
	 * @var string $transient_key
	 */
	private const TRANSIENT_KEY = 'cnhsa_federation_local_url';


	/**
	 * Activation callback: initialize default options.
	 */
	public function activate() {
		$defaults = array(
			// selected environments to federate to
			'environments' => array(),
			// credentials per environment
			'credentials'  => array(
				'production'  => array(
					'username'     => '',
					'app_password' => '',
				),
				'staging'     => array(
					'username'     => '',
					'app_password' => '',
				),
				'development' => array(
					'username'     => '',
					'app_password' => '',
				),
				'local'       => array(
					'username'     => '',
					'app_password' => '',
				),
			),
			'instructions' => "Create an application password in your profile and paste it here.\nVisit Users → Profile → Application Passwords.",
		);

		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option( self::OPTION_KEY, $defaults );
		} else {
			$opts = get_option( self::OPTION_KEY, array() );
			$opts = wp_parse_args( $opts, $defaults );
			update_option( self::OPTION_KEY, $opts );
		}
		flush_rewrite_rules();
	}

	/**
	 * Uninstall callback: clean up options and transients.
	 */
	public static function uninstall() {
		delete_option( self::OPTION_KEY );
		delete_transient( self::TRANSIENT_KEY );
		flush_rewrite_rules();
	}

	/**
	 * Bootstrap the plugin (called on 'plugins_loaded' action).
	 */
	public function load_plugin() {
		add_action( 'rest_api_init', array( $this, 'load_required_rest_routes' ) );
		$this->load_admin_screen();
		$this->wire_cron_hook_callbacks();
	}

	/**
	 * Register rest routes immediately so they're available in the admin screen JS fetch calls
	 */
	public function load_required_rest_routes() {
		$wp_admin_rest_router = new WP\AdminScreen\Rest_Router( self::OPTION_KEY, self::TRANSIENT_KEY );
		$cnhsa_rest_router    = new Transport\Rest_Router();
		$wp_admin_rest_router->register_routes();
		$cnhsa_rest_router->register_routes();
	}

	/**
	 * Load Admin Screen Interface
	 */
	private function load_admin_screen() {
		$admin_screen = new Admin_Screen( self::OPTION_KEY, self::TRANSIENT_KEY );
		add_action( 'admin_menu', array( $admin_screen, 'register_menus' ) );
		add_action( 'admin_init', array( $admin_screen, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $admin_screen, 'load_required_assets' ) );
	}

	/**
	 * Main plugin logic: wire up cron hook callbacks with their respective handlers and dependencies
	 * Allows for async POSTing of data to federated sites on save of services and locations, powered by WP Cron and Transporters.
	 */
	private function wire_cron_hook_callbacks() {
		$notifier                 = new Notifier( array( 'kroelke@choctawnation.com', 'bperkins@choctawnation.com' ) );
		$scheduler                = new Scheduler( $notifier );
		$environment              = wp_get_environment_type();
		$id_resolver              = new ID_Resolver( $environment, $notifier );
		$service_payload_factory  = new WP\Payload\Service_Payload_Factory();
		$service_publisher        = new Transport\Http\Service_Publisher( $environment, $id_resolver, $service_payload_factory, $notifier );
		$location_payload_factory = new WP\Payload\Location_Payload_Factory();
		$location_publisher       = new Transport\Http\Location_Publisher( $environment, $id_resolver, $location_payload_factory, $notifier );
		$cron                     = new Cron_Handler( $scheduler, $service_publisher, $location_publisher );
		$cron->wire_callbacks();
	}
}
