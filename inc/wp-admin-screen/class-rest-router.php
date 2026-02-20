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

		$output                 = array();
		$output['username']     = isset( $params['username'] ) ? sanitize_text_field( $params['username'] ) : '';
		$output['app_password'] = isset( $params['app_password'] ) ? sanitize_text_field( $params['app_password'] ) : '';
		$output['instructions'] = isset( $params['instructions'] ) ? sanitize_textarea_field( $params['instructions'] ) : '';

		$allowed  = array( 'production', 'staging', 'development' );
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

		update_option( 'cnhsa_federation_options', $output );

		return rest_ensure_response( $output );
	}
}
