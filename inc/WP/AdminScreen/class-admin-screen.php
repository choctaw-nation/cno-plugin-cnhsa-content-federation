<?php
/**
 * Admin screen and settings registration for CNHSA Federation.
 *
 * @package CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation\WP\AdminScreen;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Screen
 *
 * Handles the admin menu and settings for the CNHSA Federation plugin.
 */
class Admin_Screen {
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
	 * Register admin menu and submenu pages.
	 */
	public function register_menus() {
		$cap = 'manage_options';
		add_menu_page(
			'CNHSA Federation',
			'CNHSA Federation',
			$cap,
			'cnhsa-federation',
			array( $this, 'render_overview' ),
			'dashicons-networking',
			75
		);
		add_submenu_page(
			'cnhsa-federation',
			'Settings',
			'Settings',
			$cap,
			'cnhsa-federation-settings',
			array( $this, 'render_settings_page' )
		);

		// Remove the automatically added parent duplicate submenu so the menu
		// reads "CNHSA Federation -> Settings" with a single child entry.
		remove_submenu_page( 'cnhsa-federation', 'cnhsa-federation' );
	}

	/**
	 * Register settings, sections, and fields.
	 */
	public function register_settings() {
		register_setting(
			'cnhsa_federation_settings',
			'cnhsa_federation_options',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'cnhsa_federation_main',
			'Federation Credentials',
			function () {
				printf(
					'<p>Create an application password in your %s and paste it here.<br/><em>Note, you must have Administrator privileges on the CNHSA site for your application password to work.</em></p>',
					sprintf(
						'<a href="%s" target="_blank">%s</a>',
						esc_url( 'https://www.cnhsa.com/wp-admin/profile.php#application-passwords-section' ),
						esc_html( 'CNHSA User Profile page' )
					)
				);
			},
			'cnhsa-federation-settings'
		);

		// Credentials field: per-environment username/app_password
		add_settings_field(
			'credentials',
			'Environment Credentials',
			array( $this, 'field_credentials_cb' ),
			'cnhsa-federation-settings',
			'cnhsa_federation_main'
		);

		add_settings_section(
			'cnhsa_federation_targets',
			'Federation Targets',
			function () {
				echo '<p>Select which environments content should be federated to.</p>';
			},
			'cnhsa-federation-settings'
		);

		add_settings_field( 'environments', 'Environments', array( $this, 'field_environments_cb' ), 'cnhsa-federation-settings', 'cnhsa_federation_targets' );
		add_settings_field( 'local', 'Local Environment URL', array( $this, 'field_local_cb' ), 'cnhsa-federation-settings', 'cnhsa_federation_targets' );
	}

	/**
	 * Enqueue admin screen assets, but only on our plugin's settings page.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function load_required_assets( string $hook_suffix ) {
		if ( 'cnhsa-federation_page_cnhsa-federation-settings' !== $hook_suffix ) {
			return;
		}

		$asset_file         = require_once dirname( __DIR__, 3 ) . '/build/index.asset.php';
		$plugin_assets_path = dirname( __DIR__, 2 );
		$asset_name         = 'cnhsa-federation-admin';
		wp_enqueue_script(
			$asset_name,
			plugin_dir_url( $plugin_assets_path ) . 'build/index.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			array(
				'strategy' => 'defer',
			)
		);
		wp_enqueue_style(
			$asset_name,
			plugin_dir_url( $plugin_assets_path ) . 'build/index.css',
			array(),
			$asset_file['version']
		);
		wp_add_inline_script(
			$asset_name,
			'const cnhsaFederationSettings = ' . wp_json_encode( array( 'environment' => wp_get_environment_type() ) )
		);
	}

	/**
	 * Sanitize and validate options input.
	 *
	 * @param array $input The raw input from the settings form.
	 * @return array The sanitized and validated options.
	 */
	public function sanitize_options( $input ) {
		$output = array();

		// Environments: allow multiple selections from a fixed whitelist.
		$allowed  = array( 'production', 'staging', 'development', 'local' );
		$selected = array();
		if ( isset( $input['environments'] ) ) {
			if ( is_array( $input['environments'] ) ) {
				$raw = $input['environments'];
			} else {
				$raw = array( $input['environments'] );
			}
			foreach ( $raw as $val ) {
				$val = sanitize_text_field( $val );
				if ( in_array( $val, $allowed, true ) ) {
					$selected[] = $val;
				}
			}
		}
		$output['environments'] = $selected;

		// Local URL: store in a transient that expires after 30 days instead
		if ( isset( $input['localUrl'] ) ) {
			$local = esc_url_raw( $input['localUrl'] );
			set_transient( $this->transient_key, $local, DAY_IN_SECONDS * 30 );
		}

		// Credentials per environment
		$creds_out = array();
		if ( isset( $input['credentials'] ) && is_array( $input['credentials'] ) ) {
			foreach ( $input['credentials'] as $env => $creds ) {
				if ( ! in_array( $env, $allowed, true ) ) {
					continue;
				}
				$creds_out[ $env ] = array(
					'username'     => isset( $creds['username'] ) ? sanitize_text_field( $creds['username'] ) : '',
					'app_password' => isset( $creds['app_password'] ) ? sanitize_text_field( $creds['app_password'] ) : '',
				);
			}
		}
		// Back-compat: if top-level username/app_password present, apply to selected environments
		if ( empty( $creds_out ) && ( isset( $input['username'] ) || isset( $input['app_password'] ) ) ) {
			$global_user = isset( $input['username'] ) ? sanitize_text_field( $input['username'] ) : '';
			$global_pass = isset( $input['app_password'] ) ? sanitize_text_field( $input['app_password'] ) : '';
			foreach ( $selected as $env ) {
				$creds_out[ $env ] = array(
					'username'     => $global_user,
					'app_password' => $global_pass,
				);
			}
		}
		$output['credentials'] = $creds_out;

		return $output;
	}

	/**
	 * Render credentials inputs for each environment.
	 */
	public function field_credentials_cb() {
		$opts    = get_option( 'cnhsa_federation_options', array() );
		$creds   = isset( $opts['credentials'] ) ? (array) $opts['credentials'] : array();
		$choices = array(
			'production'  => 'Production',
			'staging'     => 'Staging',
			'development' => 'Development',
			'local'       => 'Local',
		);
		foreach ( $choices as $key => $label ) {
			$user = isset( $creds[ $key ]['username'] ) ? $creds[ $key ]['username'] : '';
			$pass = isset( $creds[ $key ]['app_password'] ) ? $creds[ $key ]['app_password'] : '';
			echo '<div style="margin-bottom:12px;">';
			echo '<strong>' . esc_html( $label ) . '</strong>';
			echo '<div style="margin-top:6px;">';
			printf( '<label style="display:block; margin:2px 0;">Username <input type="text" name="cnhsa_federation_options[credentials][%1$s][username]" value="%2$s" class="regular-text" /></label>', esc_attr( $key ), esc_attr( $user ) );
			printf( '<label style="display:block; margin:2px 0;">Application Password <input type="text" name="cnhsa_federation_options[credentials][%1$s][app_password]" value="%2$s" class="regular-text" /></label>', esc_attr( $key ), esc_attr( $pass ) );
			echo '</div>';
			echo '</div>';
		}
	}

	/**
	 * Render local URL input for local target.
	 */
	public function field_local_cb() {
		// Prefer transient-stored local URL (expires after 30 days), fall back to saved option for back-compat
		$val = get_transient( $this->transient_key );
		if ( false === $val ) {
			$opts = get_option( $this->option_key, array() );
			$val  = isset( $opts['localUrl'] ) ? $opts['localUrl'] : '';
		}
		printf( '<input type="text" name="cnhsa_federation_options[localUrl]" value="%s" class="regular-text" />', esc_attr( $val ) );
	}

	/**
	 * Callback for the username field.
	 */
	public function field_username_cb() {
		$opts = get_option( $this->option_key, array() );
		$val  = isset( $opts['username'] ) ? $opts['username'] : '';
		printf( '<input type="text" name="cnhsa_federation_options[username]" value="%s" class="regular-text" />', esc_attr( $val ) );
	}

	/**
	 * Callback for the application password field.
	 */
	public function field_app_password_cb() {
		$opts = get_option( $this->option_key, array() );
		$val  = isset( $opts['app_password'] ) ? $opts['app_password'] : '';
		printf( '<input type="text" name="cnhsa_federation_options[app_password]" value="%s" class="regular-text" />', esc_attr( $val ) );
	}

	/**
	 * Callback for the environments select (multi-select).
	 */
	public function field_environments_cb() {
		$opts     = get_option( $this->option_key, array() );
		$selected = isset( $opts['environments'] ) ? (array) $opts['environments'] : array();
		$choices  = array(
			'production'  => 'Production',
			'staging'     => 'Staging',
			'development' => 'Development',
		);
		foreach ( $choices as $key => $label ) {
			$checked = in_array( $key, $selected, true ) ? ' checked' : '';
			printf(
				'<label style="display:block; margin:2px 0;"><input type="checkbox" name="cnhsa_federation_options[environments][]" value="%s"%s /> %s</label>',
				esc_attr( $key ),
				$checked,
				esc_html( $label )
			);
		}
		echo '<p class="description">Check environments to federate content to.</p>';
	}

	/**
	 * Render the overview page content.
	 */
	public function render_overview() {
		echo '<div class="wrap"><h1>CNHSA Federation</h1><p>Manage CNHSA federation settings and status.</p></div>';
	}
	/**
	 * Render the settings page content.
	 */
	public function render_settings_page() {
		ob_start();
		require_once __DIR__ . '/settings-page-render-callback.php';
		echo ob_get_clean();
	}
}
