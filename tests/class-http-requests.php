<?php
/**
 * HTTP Requests Class
 * Utility class to generate types of HTTP responses in tests.
 *
 * @package ChoctawNation\CNHSA_Federation\Tests
 */

namespace ChoctawNation\CNHSA_Federation\Tests;

/**
 * Class HTTP_Requests
 */
class HTTP_Requests {
	/**
	 * Calls `pre_http_request` filter to provide a fake successful HTTP response for tests.
	 *
	 * @param array $data The data to include in the response body.
	 * @param int   $response The HTTP status code to return. Default is 200 (OK).
	 * @param array $headers Optional headers to include in the response.
	 */
	public static function successful_request( array $data, int $response = 200, array $headers = array() ) {
		add_filter( 'pre_http_request', fn( $_, $parsed_args, $url ) => self::generate_response_array( $data, $response, $headers, $parsed_args, $url ), 10, 3 );
	}

	/**
	 * Calls `pre_http_request` filter to provide a fake failed HTTP response for tests.
	 *
	 * @param string|array $message The message string or custom body to include in the response body.
	 * @param int          $response The HTTP status code to return. Default is 500 (Internal Server Error).
	 * @param array        $headers Optional headers to include in the response.
	 */
	public static function failed_request( string|array $message = 'Error occurred', int $response = 500, array $headers = array(), ) {
		$data = is_array( $message ) ? $message : array( 'message' => $message );
		add_filter( 'pre_http_request', fn( $_, $parsed_args, $url ) => self::generate_response_array( $data, $response, $headers, $parsed_args, $url ), 10, 3 );
	}

	/**
	 * Clear all filters for 'pre_http_request' to reset the state after tests.
	 */
	public static function clear_filters() {
		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Allows passing a custom callback to the 'pre_http_request' filter for more complex test scenarios.
	 *
	 * @param callable $callback The callback to use for filtering HTTP requests.
	 */
	public static function custom_callback_request( callable $callback ) {
		add_filter( 'pre_http_request', $callback, 10, 3 );
	}

	/**
	 * Prepare a response array in the format expected by the 'pre_http_request' filter with some default headers and body encoding.
	 *
	 * @param array $data The data to include in the response body.
	 * @param int   $response The HTTP status code to return.
	 * @param array $headers Optional headers to include in the response.
	 */
	public static function generate_response_array( array $data, int $response, array $headers ) {
		$headers = array_merge(
			$headers,
			array(
				'content-type' => 'application/json; charset=' . get_option( 'blog_charset' ),
			)
		);
		return array(
			'body'     => wp_json_encode( $data ),
			'response' => array( 'code' => $response ),
			'headers'  => $headers,
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
