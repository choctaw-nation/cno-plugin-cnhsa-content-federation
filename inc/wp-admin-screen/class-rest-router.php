<?php
/**
 * REST API routes for CNHSA Federation plugin.
 *
 * @package CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation\WP_Admin_Screen;

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
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response|WP_Error The response or error.
	 */
	public function get_settings( WP_REST_Request $request ) {
		$opts = get_option( 'cnhsa_federation_options', array() );
		// Prefer transient-stored local URL (expires after 30 days), fall back to saved option for back-compat
		$local = get_transient( 'cnhsa_federation_local_url' );
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
		} else {
			// Back-compat: if top-level username/app_password provided, apply to selected envs
			$global_user = isset( $params['username'] ) ? sanitize_text_field( $params['username'] ) : '';
			$global_pass = isset( $params['app_password'] ) ? sanitize_text_field( $params['app_password'] ) : '';
			if ( $global_user || $global_pass ) {
				foreach ( $selected as $env ) {
					$creds_out[ $env ] = array(
						'username'     => $global_user,
						'app_password' => $global_pass,
					);
				}
			}
		}
		$output['credentials'] = $creds_out;

		// Local URL: store in transient for 30 days if provided (do not persist permanently)
		if ( isset( $params['localUrl'] ) ) {
			$local = esc_url_raw( $params['localUrl'] );
			set_transient( 'cnhsa_federation_local_url', $local, DAY_IN_SECONDS * 30 );
		}

		update_option( 'cnhsa_federation_options', $output );

		// include transient value in response if set
		$trans_local = get_transient( 'cnhsa_federation_local_url' );
		if ( false !== $trans_local ) {
			$output['localUrl'] = $trans_local;
		}

		return rest_ensure_response( $output );

		return rest_ensure_response( $output );
	}
}
