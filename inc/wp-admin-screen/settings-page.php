<?php
/**
 * Settings page for CNHSA Federation plugin.
 * Called via Admin_Screen::render_settings_page() callback.
 *
 * @package CNHSA_Federation
 */

?>
<div class="wrap">
	<h1>CNHSA Federation Settings</h1>

	<!-- React app mounts here -->
	<div id="cnhsa-federation-app" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" data-rest-url="<?php echo 'cnhsa-federation/v1/settings'; ?>"></div>

	<noscript>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'cnhsa_federation_settings' );
			do_settings_sections( 'cnhsa-federation-settings' );
			do_settings_sections( 'cnhsa-federation-targets' );
			submit_button();
			?>
		</form>
	</noscript>
</div>
<?php
