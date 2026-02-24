<?php
/**
 * Class: Rest Router
 * Handles REST API routing between the CNO & CNHSA themes.
 *
 * @package ChoctawNation
 * @subpackage CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation\Transport;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Rest_Router
 * Manages REST API routes for the CNHSA Federation.
 */
class Rest_Router extends WP_REST_Controller {
	/**
	 * Version of the REST API.
	 *
	 * @var int $version
	 */
	private int $version = 1;

	/**
	 * Base URL for the REST API.
	 *
	 * @var string $base_url
	 */
	private string $base_url;

	/**
	 * Constructor to initialize the REST Router.
	 */
	public function __construct() {
		$this->namespace = 'cnhsa';
		$this->base_url  = "{$this->namespace}/v{$this->version}";
	}

	/**
	 * Register the REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->base_url,
			'/eligibility-guidelines',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'update_guidelines' ),
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'args'                => array(
					'content' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'wp_kses_post',
					),
				),
			)
		);
	}

	/**
	 * Update the eligibility guidelines content.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The REST response object.
	 */
	public function update_guidelines( WP_REST_Request $request ): WP_REST_Response {
		$content = $request->get_param( 'content' );
		update_field( 'cnhsa_guidelines', $content, 'options' );
		return new WP_REST_Response( array( 'message' => 'Guidelines updated successfully.' ), 201 );
	}
}
