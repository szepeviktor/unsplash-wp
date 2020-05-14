<?php
/**
 * Settings class.
 *
 * @package Unsplash
 */

namespace Unsplash;

/**
 * Plugin Settings.
 */
class Settings {

	/**
	 * Plugin interface.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Key to use for encryption.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Salt to use for encryption.
	 *
	 * @var string
	 */
	private $salt;

	/**
	 * Global parent application redirect_uri.
	 *
	 * @var string
	 */
	private $auth_redirect_uri;

	/**
	 * Global parent application client_id.
	 *
	 * @var string
	 */
	private $auth_client_id = '38mSDjSiO3qXfUf_o8zHyww7e2UX-zeJV5DpWCJjHQE';

	/**
	 * Global parent application client_secret.
	 *
	 * @var string
	 */
	private $auth_client_secret = 'D-S-kaH92uWKfo956txd9zBmPz-YgmBETdo_xE3TwxA';

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Instance of the plugin abstraction.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
		$this->key    = $this->get_default_key();
		$this->salt   = $this->get_default_salt();

		// Set the redirect_uri.
		$this->auth_redirect_uri = get_admin_url( null, 'options-general.php?page=unsplash' );
	}

	/**
	 * Initiate the class.
	 */
	public function init() {
		$this->plugin->add_doc_hooks( $this );
	}

	/**
	 * Encrypts a value.
	 *
	 * If a user-based key is set, that key is used. Otherwise the default key is used.
	 *
	 * @param string $value Value to encrypt.
	 * @return string|bool Encrypted value, or false on failure.
	 */
	public function encrypt( $value ) {
		if ( ! extension_loaded( 'openssl' ) ) {
			return $value;
		}

		$method = 'aes-256-ctr';
		$ivlen  = openssl_cipher_iv_length( $method );
		$iv     = openssl_random_pseudo_bytes( $ivlen );

		$raw_value = openssl_encrypt( $value . $this->salt, $method, $this->key, 0, $iv );
		if ( ! $raw_value ) {
			return false;
		}

		return base64_encode( $iv . $raw_value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypts a value.
	 *
	 * If a user-based key is set, that key is used. Otherwise the default key is used.
	 *
	 * @param string $raw_value Value to decrypt.
	 * @return string|bool Decrypted value, or false on failure.
	 */
	public function decrypt( $raw_value ) {
		if ( ! extension_loaded( 'openssl' ) ) {
			return $raw_value;
		}

		$raw_value = base64_decode( $raw_value, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		$method = 'aes-256-ctr';
		$ivlen  = openssl_cipher_iv_length( $method );
		$iv     = substr( $raw_value, 0, $ivlen );

		$raw_value = substr( $raw_value, $ivlen );

		$value = openssl_decrypt( $raw_value, $method, $this->key, 0, $iv );
		if ( ! $value || substr( $value, - strlen( $this->salt ) ) !== $this->salt ) {
			return false;
		}

		return substr( $value, 0, - strlen( $this->salt ) );
	}

	/**
	 * Gets the default encryption key to use.
	 *
	 * @return string Default (not user-based) encryption key.
	 */
	private function get_default_key() {
		if ( defined( 'UNSPLASH_ENCRYPTION_KEY' ) && '' !== UNSPLASH_ENCRYPTION_KEY ) {
			return UNSPLASH_ENCRYPTION_KEY;
		}

		if ( defined( 'LOGGED_IN_KEY' ) && '' !== LOGGED_IN_KEY ) {
			return LOGGED_IN_KEY;
		}

		// If this is reached, you're either not on a live site or have a serious security issue.
		return 'there-is-no-secret-key';
	}

	/**
	 * Gets the default encryption salt to use.
	 *
	 * @return string Encryption salt.
	 */
	private function get_default_salt() {
		if ( defined( 'UNSPLASH_ENCRYPTION_SALT' ) && '' !== UNSPLASH_ENCRYPTION_SALT ) {
			return UNSPLASH_ENCRYPTION_SALT;
		}

		if ( defined( 'LOGGED_IN_SALT' ) && '' !== LOGGED_IN_SALT ) {
			return LOGGED_IN_SALT;
		}

		// If this is reached, you're either not on a live site or have a serious security issue.
		return 'there-is-no-secret-salt';
	}

	/**
	 * Adds the Unsplash admin menu.
	 *
	 * @action admin_menu
	 */
	public function add_admin_menu() {
		add_options_page( 'Unsplash', 'Unsplash', 'manage_options', 'unsplash', [ $this, 'settings_page_render' ] );
	}

	/**
	 * Add the Unsplash settings.
	 *
	 * @action admin_init
	 */
	public function add_settings() {
		$args = [
			'sanitize_callback' => [ $this, 'sanitize_settings' ],
		];
		register_setting( 'unsplash', 'unsplash_settings', $args );

		add_settings_section(
			'unsplash_section',
			esc_html__( 'Manual API Authentication', 'unsplash' ),
			[ $this, 'settings_section_render' ],
			'unsplash'
		);

		add_settings_field(
			'access_key',
			esc_html__( 'Access Key', 'unsplash' ),
			[ $this, 'access_key_render' ],
			'unsplash',
			'unsplash_section'
		);
	}

	/**
	 * Sanitize the Unsplash settings.
	 *
	 * @param array $settings Values being stored in the DB.
	 * @return array Sanitized and encrypted values.
	 */
	public function sanitize_settings( $settings ) {
		$options = get_option( 'unsplash_settings' );

		foreach ( $settings as $key => $value ) {
			$should_encrypt = (
				'access_key' === $key
				&& ! empty( $value )
				&& (
					! isset( $options[ $key ] )
					|| $options[ $key ] !== $value
				)
			);

			if ( $should_encrypt ) {
				$settings[ $key ] = $this->encrypt( $value );
			} else {
				$settings[ $key ] = sanitize_text_field( $value );
			}
		}

		return $settings;
	}

	/**
	 * Renders the entire settings page.
	 */
	public function settings_page_render() {
		$logo = $this->plugin->asset_url( 'assets/images/logo.svg' );
		$auth = get_option( 'unsplash_auth' );
		if ( ! empty( $auth['message'] ) ) {
			printf( '<div class="%s notice is-dismissible"><p>%s</p></div>', esc_attr( $auth['type'] ), esc_html( $auth['message'] ) );
			delete_option( 'unsplash_auth' );
		}
		?>
		<style>
			.notice, div.error, div.updated {
				margin: 18px 18px 2px 0px;
			}
			p.submit{
				margin-top: 0px;
				margin-bottom: 20px;
			}
		</style>
		<h1><img src="<?php echo esc_url( $logo ); ?>" height="26" />  <?php esc_html_e( 'Unsplash', 'unsplash' ); ?></h1>
		<p><i><?php esc_html_e( 'Search the internet’s source of freely usable images.', 'unsplash' ); ?></i></p><br />
		<h1><?php esc_html_e( 'General Settings', 'unsplash' ); ?></h1>
		<?php

		$settings = get_option( 'unsplash_settings' );
		$register = sprintf(
			'<p class="submit"><a href="https://unsplash.com/oauth/authorize?client_id=%1$s&response_type=code&scope=public&redirect_uri=%2$s" class="button button-primary">%3$s</a></p>',
			esc_html( $this->auth_client_id ),
			urlencode( wp_nonce_url( $this->auth_redirect_uri, 'auth' ) ),
			esc_html__( 'Authenticate', 'unsplash' )
		);
		if ( empty( $settings['access_key'] ) ) {
			?>
			<div class="notice notice-warning">
				<h3><?php esc_html_e( 'Unsplash set up is incomplete', 'unsplash' ); ?></h3>
				<p><?php esc_html_e( 'To complete set up of the Unsplash plugin you will need to authenticate with an API key. Proceed to automatically set up authentication.', 'unsplash' ); ?></p>
				<?php echo $register; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
			<?php
		} elseif ( ! $this->plugin->rest_controller->api->check_api_status() ) {
			?>
				<div class="notice notice-error">
					<h3><?php esc_html_e( 'Unable to connect to Unsplash', 'unsplash' ); ?> <span class="dashicons dashicons-dismiss" style="color: #dc3232"></span> </h3>
					<p><?php esc_html_e( 'Unsplash set up has failed. Please automatically set up authentication again.', 'unsplash' ); ?></p>
					<?php echo $register; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<?php
		} else {
			?>
			<br />
			<h3><?php esc_html_e( 'Unsplash set up is complete', 'unsplash' ); ?> <span class="dashicons dashicons-yes-alt" style="color: #46b450"></span></h3>
			<p><?php esc_html_e( 'If you want to change something, you can always deauthenticate your account and reauthenticate.', 'unsplash' ); ?></p>
			<form action='options.php' method='post' style="max-width: 800px">
				<input type='hidden' name='unsplash_settings[access_key]' value='' />
				<?php
				settings_fields( 'unsplash' );
				submit_button( __( 'Deauthenticate', 'unsplash' ), '' );
				?>
			</form>
			<?php
		}
		?>
		<br />
		<form action='options.php' method='post' style="max-width: 800px">
			<?php
			settings_fields( 'unsplash' );
			do_settings_sections( 'unsplash' );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Redirect page with wp_safe_redirect.
	 *
	 * @codeCoverageIgnore
	 */
	public function redirect() {
		if ( wp_safe_redirect( $this->auth_redirect_uri ) ) {
			exit;
		}
	}

	/**
	 * Update auth options and redirect.
	 *
	 * @param string $message The notice message.
	 * @param string $type The notice type. Default: error.
	 */
	public function redirect_auth( $message = '', $type = 'error' ) {
		update_option(
			'unsplash_auth',
			[
				'message' => $message,
				'type'    => $type,
			]
		);

		$this->redirect();
	}

	/**
	 * Handles the authentication flow for registering a dynamic client application.
	 *
	 * @action admin_init
	 */
	public function handle_auth_flow() {
		$code = $this->get_code();

		if ( $code ) {
			$client_id = $this->get_client_id( $this->get_access_token( $code ) );

			if ( $client_id ) {
				remove_filter( 'sanitize_option_unsplash_settings', [ $this, 'sanitize_settings' ] );
				update_option(
					'unsplash_settings',
					[
						'access_key' => $this->encrypt( $client_id ),
					]
				);
				add_filter( 'sanitize_option_unsplash_settings', [ $this, 'sanitize_settings' ] );

				$credentials = [
					'applicationId' => $client_id,
					'utmSource'     => 'WordPress',
				];

				$api = $this->plugin->rest_controller->api;

				if ( true !== $api->check_api_credentials() || true !== $api->check_api_status( $credentials ) ) {
					$this->redirect_auth( esc_html__( 'Unsplash setup has failed, could not connect to the Unsplash API.', 'unsplash' ) );
					return false;
				}

				$this->redirect_auth( esc_html__( 'Your Client Application was successfully created, and is now connected to the Unsplash API.', 'unsplash' ), 'updated' );
			}
		}
	}

	/**
	 * Get the code during the auth flow.
	 *
	 * @return mixed
	 */
	public function get_code() {
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		$code  = isset( $_REQUEST['code'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['code'] ) ) : '';

		if ( $nonce && wp_verify_nonce( $nonce, 'auth' ) && ! empty( $code ) ) {
			return $code;
		}

		return false;
	}

	/**
	 * Get the access_token during the auth flow.
	 *
	 * @param string $code The auth code.
	 * @return mixed
	 */
	public function get_access_token( $code ) {
		$response = wp_remote_post(
			'https://unsplash.com/oauth/token',
			[
				'body' => [
					'client_id'     => $this->auth_client_id,
					'client_secret' => $this->auth_client_secret,
					'redirect_uri'  => wp_nonce_url( $this->auth_redirect_uri, 'auth' ),
					'code'          => $code,
					'grant_type'    => 'authorization_code',
				],
			]
		);

		$error = esc_html__( 'Could not generate an Unsplash API access_token.', 'unsplash' );

		if ( ! is_array( $response ) || is_wp_error( $response ) ) {
			$this->redirect_auth( $error );
			return false;
		}

		// Setup the result from the response body.
		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_wp_error( $result ) && is_array( $result ) && isset( $result['access_token'] ) ) {
			return $result['access_token'];
		}

		// Handle error message from API.
		if ( is_array( $result ) && isset( $result['error_description'] ) ) {
			$this->redirect_auth( esc_html( $result['error_description'] ) );
			return false;
		}

		$this->redirect_auth( $error );
		return false;
	}

	/**
	 * Get the client_id during the auth flow.
	 *
	 * @param string $access_token The access token.
	 * @return mixed
	 */
	public function get_client_id( $access_token ) {
		$response = wp_remote_post(
			'https://api.unsplash.com/clients',
			[
				'body'    => [
					'name'        => 'WordPress OAuth',
					'description' => 'Client application for ' . get_bloginfo( 'name' ) . ' - ' . get_home_url( null, '/' ),
				],
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
				],
			]
		);

		$error = esc_html__( 'Could not generate an Unsplash API client_id.', 'unsplash' );
		$code  = wp_remote_retrieve_response_code( $response );

		if ( ! is_array( $response ) || is_wp_error( $response ) || ! in_array( $code, [ 200, 201 ] ) ) {
			$this->redirect_auth( $error );
			return false;
		}

		// Setup the result from the response body.
		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_wp_error( $result ) && is_array( $result ) && isset( $result['client_id'] ) ) {
			return $result['client_id'];
		}

		$this->redirect_auth( $error );
		return false;
	}

	/**
	 * Renders the settings section.
	 */
	public function settings_section_render() {
		/* translators: %s: Link to OAuth Applications page. */
		echo '<p>' . esc_html__( 'Always use the default automated set up unless a manual authentication process is required. ', 'unsplash' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Renders the Access Key.
	 */
	public function access_key_render() {
		$options = get_option( 'unsplash_settings' );
		?>
		<input type='password' class="widefat" name='unsplash_settings[access_key]' aria-describedby="unsplash-key-description" value='<?php echo esc_attr( isset( $options['access_key'] ) ? $options['access_key'] : '' ); ?>'>
		<p class="description" id="unsplash-key-description"><?php esc_html_e( 'Only use if you have an API key to manually enter for authentication.', 'unsplash' ); ?></p>
		<?php
	}

	/**
	 * Format the API credentials in an array and filter.
	 *
	 * @return mixed|array
	 */
	public function get_credentials() {
		$options        = get_option( 'unsplash_settings' );
		$site_name_slug = sanitize_title_with_dashes( get_bloginfo( 'name' ) );

		$credentials = [
			'applicationId' => ! empty( $options['access_key'] ) ? $this->decrypt( $options['access_key'] ) : getenv( 'UNSPLASH_ACCESS_KEY' ),
			'utmSource'     => getenv( 'UNSPLASH_UTM_SOURCE' ) ? getenv( 'UNSPLASH_UTM_SOURCE' ) : $site_name_slug,
		];

		/**
		 * Filter API credentials.
		 *
		 * @param array $credentials Array of API credentials.
		 * @param array $options Unsplash settings.
		 */
		$credentials = apply_filters( 'unsplash_api_credentials', $credentials, $options );

		return $credentials;
	}
}
