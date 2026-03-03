<?php
/**
 * REST API routes for CNHSA Federation plugin.
 *
 * @package CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation\WP\AdminScreen;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Rest_Router
 *
 * Registers REST routes for the plugin.
 */
class Rest_Router extends WP_REST_Controller {
	/**
	 * Option key for storing plugin settings.
	 *
	 * @var string $option_key
	 */
	private readonly string $option_key;

	/**
	 * Transient key for storing local URL.
	 *
	 * @var string $transient_key
	 */
	private readonly string $transient_key;

	/**
	 * Constructor to initialize option and transient keys.
	 *
	 * @param string $option_key The option key for storing settings.
	 * @param string $transient_key The transient key for storing local URL.
	 */
	public function __construct( string $option_key, string $transient_key ) {
		$this->option_key    = $option_key;
		$this->transient_key = $transient_key;
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			'cnhsa-federation/v1',
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
				),
			)
		);
	}

	/**
	 * GET callback.
	 *
	 * @return WP_REST_Response|WP_Error The response or error.
	 */
	public function get_settings() {
		$opts = get_option( $this->option_key, array() );
		// Prefer transient-stored local URL (expires after 30 days), fall back to saved option for back-compat
		$local = get_transient( $this->transient_key );
		if ( false !== $local ) {
			$opts['localUrl'] = $local;
		} elseif ( isset( $opts['localUrl'] ) ) {
			$opts['localUrl'] = $opts['localUrl'];
		}
		return rest_ensure_response( $opts );
	}

	/**
	 * POST/PUT callback.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response|WP_Error The response or error.
	 */
	public function update_settings( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return new WP_Error( 'invalid_data', 'Invalid request payload', array( 'status' => 400 ) );
		}

		$output = array();

		// Selected environments
		$allowed  = array( 'production', 'staging', 'development', 'local' );
		$selected = array();
		if ( isset( $params['environments'] ) && is_array( $params['environments'] ) ) {
			foreach ( $params['environments'] as $val ) {
				$val = sanitize_text_field( $val );
				if ( in_array( $val, $allowed, true ) ) {
					$selected[] = $val;
				}
			}
		}
		$output['environments'] = $selected;

		// Credentials per environment: accept `credentials` map if provided
		$creds_out = array();
		if ( isset( $params['credentials'] ) && is_array( $params['credentials'] ) ) {
			foreach ( $params['credentials'] as $env => $creds ) {
				if ( ! in_array( $env, array( 'production', 'staging', 'development', 'local' ), true ) ) {
					continue;
				}
				$creds_out[ $env ] = array(
					'username'     => isset( $creds['username'] ) ? sanitize_text_field( $creds['username'] ) : '',
					'app_password' => isset( $creds['app_password'] ) ? sanitize_text_field( $creds['app_password'] ) : '',
				);
			}
		}
		$output['credentials'] = $creds_out;

		// Local URL: store in transient for 30 days if provided (do not persist permanently)
		if ( isset( $params['localUrl'] ) ) {
			$local = esc_url_raw( $params['localUrl'] );
			set_transient( $this->transient_key, $local, DAY_IN_SECONDS * 30 );
		}

		update_option( $this->option_key, $output );

		// include transient value in response if set
		$trans_local = get_transient( $this->transient_key );
		if ( false !== $trans_local ) {
			$output['localUrl'] = $trans_local;
		}

		return rest_ensure_response( $output );
	}
}
