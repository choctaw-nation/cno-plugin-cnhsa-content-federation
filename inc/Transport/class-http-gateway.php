<?php
/**
 * HTTP Gateway
 *
 * @package ChoctawNation\CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation\Transport;

use Exception;

/**
 * HTTP Gateway handles communication with the CNHSA API.
 */
class HTTP_Gateway {
	/**
	 * Base URL for the CNHSA API.
	 *
	 * @var string
	 */
	public string $base_url;

	/**
	 * Constructor.
	 *
	 * @param 'local'|'development'|'staging'|'production' $environment The environment type to determine the base URL.
	 */
	public function __construct( string $environment ) {
		$env_urls       = array(
			'production'  => 'https://www.cnhsa.com',
			'staging'     => 'https://healthclinstg.wpenginepowered.com',
			'development' => 'https://healthclindev.wpenginepowered.com',
			'local'       => get_transient( 'cnhsa_federation_local_url' ) ?: 'https://cnhsa.local', // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		);
		$this->base_url = ( $env_urls[ $environment ] ?? 'https://www.cnhsa.com' ) . '/wp-json/cnhsa/v1';
	}

	/**
	 * Gets credentials from options
	 *
	 * @return string Base64-encoded credentials for the target environment, or empty string if not set.
	 * @throws Exception If credentials are missing or invalid.
	 */
	private function get_auth(): string {
		$opts = get_option( 'cnhsa_federation_options', array() );
		$envs = $opts['environments'] ?? array();
		if ( empty( $envs ) ) {
			throw new Exception( esc_html( 'No target environments configured for federation.' ) );
		}
		$env   = $envs[0]; // For now, just use the first
		$creds = $opts['credentials'][ $env ] ?? null;
		if ( ! $creds || empty( $creds['username'] ) || empty( $creds['app_password'] ) ) {
			throw new Exception( esc_html( "Credentials for environment '{$env}' are not fully configured." ) );
		}
		$user = $creds['username'];
		$pass = $creds['app_password'];

		return base64_encode( $user . ':' . $pass ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Publishes the given content post to the remote CNHSA API.
	 *
	 * @param string $url The endpoint URL to publish to.
	 * @param array  $payload The payload data to send.
	 * @return array The response body from the CNHSA API.
	 * @throws Exception If the request fails or returns an error.
	 */
	public function publish_content( string $url, array $payload ): array {
		$response      = wp_remote_post(
			$url,
			array(
				'method'  => 'POST',
				'body'    => wp_json_encode( $payload ),
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Basic ' . $this->get_auth(),
				),
			)
		);
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( is_wp_error( $response ) || 201 !== $response_code ) {
			if ( is_wp_error( $response ) ) {
				throw new Exception( esc_html( $response->get_error_message() ) );
			}
			$body          = json_decode( wp_remote_retrieve_body( $response ), true );
			$code          = ! empty( $body['code'] ) ? $body['code'] : $response_code;
			$error_message = ! empty( $body['message'] ) ? $body['message'] : ( $body['error'] ?? 'Unknown error' );
			throw new Exception(
				sprintf(
					'%s error: %s',
					esc_textarea( $code ),
					esc_textarea( $error_message )
				)
			);
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body;
	}
}
