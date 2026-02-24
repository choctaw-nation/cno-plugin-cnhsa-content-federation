<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Abstract Publisher class for CNHSA Federation plugin.
 *
 * @package ChoctawNation
 * @subpackage CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation\Transport\Http;

use Exception;

/**
 * Class Abstract_Publisher
 */
abstract class Abstract_Publisher {
	/**
	 * Base URL for the CNHSA API.
	 *
	 * @var string
	 */
	protected string $base_url;

	/**
	 * Constructor for the Abstract_Publisher class.
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
		$this->base_url = ( $env_urls[ $environment ] ?? 'https://cnhsa.choctawnation.com' ) . '/wp-json/cnhsa/v1';
	}

	/**
	 * Gets credentials from options
	 *
	 * @return string Base64-encoded credentials for the target environment, or empty string if not set.
	 * @throws Exception If credentials are missing or invalid.
	 */
	protected function get_auth(): string {
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
}
