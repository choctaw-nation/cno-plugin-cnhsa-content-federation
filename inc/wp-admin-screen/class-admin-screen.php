<?php
/**
 * Admin screen and settings registration for CNHSA Federation.
 *
 * @package CNHSA_Federation
 */

namespace ChoctawNation\CNHSA_Federation\WP_Admin_Screen;

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
		$settings_fields = array(
			'username'     => array(
				'label'    => 'Username',
				'callback' => array( $this, 'field_username_cb' ),
			),
			'app_password' => array(
				'label'    => 'Application Password',
				'callback' => array( $this, 'field_app_password_cb' ),
			),
		);
		foreach ( $settings_fields as $id => $field ) {
			add_settings_field(
				$id,
				$field['label'],
				$field['callback'],
				'cnhsa-federation-settings',
				'cnhsa_federation_main'
			);
		}

		add_settings_section(
			'cnhsa_federation_targets',
			'Federation Targets',
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
		$targets_fields = array(
			'environments' => array(
				'label'    => 'Environments',
				'callback' => array( $this, 'field_environments_cb' ),
			),
			'local'        => array(
				'label'    => 'Local Environment URL',
				'callback' => function () {
					echo '<p class="description">Check this box to allow federation to the local environment (this site). Useful for testing.</p>';
					printf(
						'<label><input type="text" name="cnhsa_federation_options[environments][]" value="local" /> Allow local federation</label>'
					);
				},
			),
		);
		foreach ( $targets_fields as $id => $field ) {
			add_settings_field(
				$id,
				$field['label'],
				$field['callback'],
				'cnhsa-federation-settings',
				'cnhsa_federation_targets'
			);
		}
	}

	/**
	 * Sanitize and validate options input.
	 *
	 * @param array $input The raw input from the settings form.
	 * @return array The sanitized and validated options.
	 */
	public function sanitize_options( $input ) {
		$output                 = array();
		$output['username']     = isset( $input['username'] ) ? sanitize_text_field( $input['username'] ) : '';
		$output['app_password'] = isset( $input['app_password'] ) ? sanitize_text_field( $input['app_password'] ) : '';
		// Environments: allow multiple selections from a fixed whitelist.
		$allowed  = array( 'production', 'staging', 'development' );
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

		return $output;
	}

	/**
	 * Callback for the username field.
	 */
	public function field_username_cb() {
		$opts = get_option( 'cnhsa_federation_options', array() );
		$val  = isset( $opts['username'] ) ? $opts['username'] : '';
		printf( '<input type="text" name="cnhsa_federation_options[username]" value="%s" class="regular-text" />', esc_attr( $val ) );
	}

	/**
	 * Callback for the application password field.
	 */
	public function field_app_password_cb() {
		$opts = get_option( 'cnhsa_federation_options', array() );
		$val  = isset( $opts['app_password'] ) ? $opts['app_password'] : '';
		printf( '<input type="text" name="cnhsa_federation_options[app_password]" value="%s" class="regular-text" />', esc_attr( $val ) );
	}

	/**
	 * Callback for the environments select (multi-select).
	 */
	public function field_environments_cb() {
		$opts     = get_option( 'cnhsa_federation_options', array() );
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
		require_once __DIR__ . '/settings-page.php';
		echo ob_get_clean();
	}
}
