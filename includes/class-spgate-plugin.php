<?php
/**
 * Main plugin class.
 *
 * @package SitePasswordGate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates site protection and the settings screen.
 */
final class SPGATE_Plugin {

	/**
	 * Database option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'spgate_options';

	/**
	 * Singleton instance.
	 *
	 * @var SPGATE_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Password hash created during the current settings request.
	 *
	 * WordPress can invoke an option sanitizer twice while adding a new option.
	 * Keeping the new hash in memory makes the callback idempotent without ever
	 * accepting a password hash from browser input.
	 *
	 * @var string
	 */
	private $pending_password_hash = '';

	/**
	 * Return the singleton instance.
	 *
	 * @return SPGATE_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Add the default settings without overwriting existing values.
	 *
	 * @return void
	 */
	public static function activate() {
		add_option( self::OPTION_NAME, self::get_defaults(), '', true );
	}

	/**
	 * Get default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults() {
		return array(
			'enabled'         => 0,
			'password_hash'   => '',
			'duration_hours'  => 168,
			'allow_logged_in' => 1,
			'heading'         => __( 'This site is private', 'site-password-gate' ),
			'message'         => __( 'Enter the site password to continue.', 'site-password-gate' ),
		);
	}

	/**
	 * Set up hooks.
	 */
	private function __construct() {
		if ( $this->is_enabled() && ! defined( 'DONOTCACHEPAGE' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Shared constant honored by WordPress caching plugins.
			define( 'DONOTCACHEPAGE', true );
		}

		add_action( 'template_redirect', array( $this, 'maybe_show_gate' ), -999 );
		add_filter( 'rest_authentication_errors', array( $this, 'protect_rest_api' ), 99 );
		add_filter( 'robots_txt', array( $this, 'filter_robots_txt' ), 99, 2 );
		add_filter( 'wp_sitemaps_enabled', array( $this, 'filter_sitemaps_enabled' ) );

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'ensure_options_exist' ), 5 );
			add_action( 'admin_init', array( $this, 'register_settings' ), 10 );
			add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
			add_filter(
				'plugin_action_links_' . plugin_basename( SPGATE_PLUGIN_FILE ),
				array( $this, 'add_plugin_action_links' )
			);
		}
	}

	/**
	 * Ensure the option exists before the Settings API attempts to update it.
	 *
	 * WordPress may sanitize a newly added option twice. Creating the option first
	 * keeps password hashing idempotent. The legacy key migration supports sites
	 * that installed an early development build of the plugin.
	 *
	 * @return void
	 */
	public function ensure_options_exist() {
		$current_options = get_option( self::OPTION_NAME, false );
		$legacy_options  = get_option( 'spg_options', false );

		if ( false !== $current_options ) {
			if (
				is_array( $current_options )
				&& empty( $current_options['password_hash'] )
				&& is_array( $legacy_options )
				&& ! empty( $legacy_options['password_hash'] )
			) {
				update_option(
					self::OPTION_NAME,
					wp_parse_args( $legacy_options, wp_parse_args( $current_options, self::get_defaults() ) )
				);
				delete_option( 'spg_options' );
			}

			return;
		}

		$options = is_array( $legacy_options )
			? wp_parse_args( $legacy_options, self::get_defaults() )
			: self::get_defaults();

		if ( add_option( self::OPTION_NAME, $options, '', true ) && is_array( $legacy_options ) ) {
			delete_option( 'spg_options' );
		}
	}

	/**
	 * Get normalized settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_options() {
		$options = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		return wp_parse_args( $options, self::get_defaults() );
	}

	/**
	 * Whether protection is configured and switched on.
	 *
	 * @return bool
	 */
	private function is_enabled() {
		// Read only the two values needed here. This method runs while plugins are
		// loading, before translated defaults should be requested.
		$options = get_option( self::OPTION_NAME, array() );

		return is_array( $options ) && ! empty( $options['enabled'] ) && ! empty( $options['password_hash'] );
	}

	/**
	 * Show the password screen when the current visitor has no access.
	 *
	 * @return void
	 */
	public function maybe_show_gate() {
		if ( ! $this->is_enabled() || $this->is_request_exempt() || $this->has_access() ) {
			return;
		}

		$error       = '';
		$status_code = 401;

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' === strtoupper( $request_method ) ) {
			$action = isset( $_POST['spgate_action'] ) ? sanitize_key( wp_unslash( $_POST['spgate_action'] ) ) : '';

			if ( 'unlock' === $action ) {
				$nonce = isset( $_POST['spgate_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['spgate_nonce'] ) ) : '';

				if ( ! wp_verify_nonce( $nonce, 'spgate_unlock_site' ) ) {
					$error       = __( 'The form expired. Please try again.', 'site-password-gate' );
					$status_code = 400;
				} else {
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords must retain every submitted character.
					$password = isset( $_POST['spgate_password'] ) ? (string) wp_unslash( $_POST['spgate_password'] ) : '';

					if ( $this->password_is_valid( $password ) ) {
						$this->grant_access();
						$redirect = isset( $_POST['spgate_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['spgate_redirect'] ) ) : '';
						$redirect = wp_validate_redirect( $redirect, home_url( '/' ) );

						wp_safe_redirect( $redirect );
						exit;
					}

					$error       = __( 'That password is not correct.', 'site-password-gate' );
					$status_code = 403;
				}
			}
		}

		$this->render_gate( $error, $status_code );
	}

	/**
	 * Return whether this request must remain available without the site password.
	 *
	 * @return bool
	 */
	private function is_request_exempt() {
		$exempt = is_admin() || wp_doing_ajax() || wp_doing_cron();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$exempt = true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$exempt = true;
		}

		global $pagenow;

		if ( in_array( $pagenow, array( 'wp-login.php', 'wp-register.php' ), true ) ) {
			$exempt = true;
		}

		/**
		 * Filter whether a request can bypass the site password screen.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $exempt Whether the request is exempt.
		 */
		return (bool) apply_filters( 'spgate_is_request_exempt', $exempt );
	}

	/**
	 * Determine whether the current visitor can view the site.
	 *
	 * Administrators always bypass the gate so the site cannot lock out its owner.
	 *
	 * @return bool
	 */
	private function has_access() {
		$options = $this->get_options();

		if ( is_user_logged_in() && ( current_user_can( 'manage_options' ) || ! empty( $options['allow_logged_in'] ) ) ) {
			return true;
		}

		return $this->has_valid_access_cookie( $options );
	}

	/**
	 * Verify a submitted password against the stored one-way hash.
	 *
	 * @param string $password Submitted password.
	 * @return bool
	 */
	private function password_is_valid( $password ) {
		$options = $this->get_options();

		return '' !== $password
			&& ! empty( $options['password_hash'] )
			&& wp_check_password( $password, $options['password_hash'] );
	}

	/**
	 * Create a signed access token and store it as a secure cookie.
	 *
	 * @return void
	 */
	private function grant_access() {
		$options  = $this->get_options();
		$duration = absint( $options['duration_hours'] ) * HOUR_IN_SECONDS;
		$expires  = time() + max( HOUR_IN_SECONDS, $duration );
		$token    = $expires . '|' . $this->sign_expiration( $expires, $options['password_hash'] );

		setcookie(
			$this->get_cookie_name(),
			$token,
			array(
				'expires'  => $expires,
				'path'     => $this->get_cookie_path(),
				'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Validate the browser access cookie.
	 *
	 * @param array<string, mixed> $options Current plugin settings.
	 * @return bool
	 */
	private function has_valid_access_cookie( $options ) {
		$cookie_name = $this->get_cookie_name();

		if ( empty( $_COOKIE[ $cookie_name ] ) ) {
			return false;
		}

		$token = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) );

		if ( ! preg_match( '/^(\d{10})\|([a-f0-9]{64})$/', $token, $matches ) ) {
			return false;
		}

		$expires = (int) $matches[1];

		if ( $expires < time() || $expires > time() + ( 31 * DAY_IN_SECONDS ) ) {
			return false;
		}

		$expected = $this->sign_expiration( $expires, $options['password_hash'] );

		return hash_equals( $expected, $matches[2] );
	}

	/**
	 * Sign an expiry timestamp. Including the password hash invalidates every access
	 * cookie as soon as the site password changes.
	 *
	 * @param int    $expires      Unix timestamp.
	 * @param string $password_hash Stored password hash.
	 * @return string
	 */
	private function sign_expiration( $expires, $password_hash ) {
		return hash_hmac( 'sha256', $expires . '|' . $password_hash, wp_salt( 'auth' ) );
	}

	/**
	 * Build a site-specific cookie name.
	 *
	 * @return string
	 */
	private function get_cookie_name() {
		$site_hash = defined( 'COOKIEHASH' ) && COOKIEHASH ? COOKIEHASH : md5( home_url( '/' ) );

		return 'spgate_access_' . $site_hash;
	}

	/**
	 * Get the path shared by all front-end URLs for this WordPress installation.
	 *
	 * @return string
	 */
	private function get_cookie_path() {
		if ( defined( 'COOKIEPATH' ) && COOKIEPATH ) {
			return COOKIEPATH;
		}

		$path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		return $path ? trailingslashit( $path ) : '/';
	}

	/**
	 * Render the standalone unlock screen.
	 *
	 * @param string $error       Optional error message.
	 * @param int    $status_code HTTP response status.
	 * @return void
	 */
	private function render_gate( $error, $status_code ) {
		$options      = $this->get_options();
		$site_name    = get_bloginfo( 'name' );
		$heading      = apply_filters( 'spgate_gate_heading', $options['heading'] );
		$message      = apply_filters( 'spgate_gate_message', $options['message'] );
		$form_action  = home_url( '/' );
		$redirect_url = $this->get_current_url();
		$site_icon    = get_site_icon_url( 64 );
		$style_url    = SPGATE_PLUGIN_URL . 'assets/css/gate.css';

		status_header( $status_code );
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		wp_enqueue_style( 'site-password-gate', $style_url, array(), SPGATE_VERSION );

		include SPGATE_PLUGIN_DIR . 'templates/gate.php';
		exit;
	}

	/**
	 * Safely reconstruct the current URL for the post-unlock redirect.
	 *
	 * @return string
	 */
	private function get_current_url() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$request_uri = is_string( $request_uri ) ? $request_uri : '/';

		if ( 0 !== strpos( $request_uri, '/' ) || 0 === strpos( $request_uri, '//' ) ) {
			$request_uri = '/';
		}

		$home = wp_parse_url( home_url( '/' ) );

		if ( empty( $home['scheme'] ) || empty( $home['host'] ) ) {
			return home_url( '/' );
		}

		$origin = $home['scheme'] . '://' . $home['host'];
		if ( ! empty( $home['port'] ) ) {
			$origin .= ':' . (int) $home['port'];
		}

		return wp_validate_redirect( $origin . $request_uri, home_url( '/' ) );
	}

	/**
	 * Require the site password for otherwise-public REST API requests.
	 *
	 * @param WP_Error|null|true $result Existing authentication result.
	 * @return WP_Error|null|true
	 */
	public function protect_rest_api( $result ) {
		if ( null !== $result || ! $this->is_enabled() || $this->has_access() ) {
			return $result;
		}

		return new WP_Error(
			'spgate_site_locked',
			__( 'This site is password protected.', 'site-password-gate' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Tell search crawlers not to index a protected site.
	 *
	 * @param string $output Robots.txt output.
	 * @param bool   $site_public Whether the site is considered public.
	 * @return string
	 */
	public function filter_robots_txt( $output, $site_public ) {
		unset( $site_public );

		if ( ! $this->is_enabled() ) {
			return $output;
		}

		return "User-agent: *\nDisallow: /\n";
	}

	/**
	 * Disable core XML sitemaps while the site is protected.
	 *
	 * @param bool $enabled Whether sitemaps are enabled.
	 * @return bool
	 */
	public function filter_sitemaps_enabled( $enabled ) {
		return $this->is_enabled() ? false : $enabled;
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'spgate_settings',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => self::get_defaults(),
			)
		);
	}

	/**
	 * Validate and normalize settings submitted by an administrator.
	 *
	 * @param mixed $input Raw settings input.
	 * @return array<string, mixed>
	 */
	public function sanitize_options( $input ) {
		$current       = $this->get_options();
		$input         = is_array( $input ) ? $input : array();
		$password_hash = is_string( $current['password_hash'] ) ? $current['password_hash'] : '';

		// Keep the result when WordPress sanitizes a newly added option twice.
		if (
			'' === $password_hash
			&& '' !== $this->pending_password_hash
			&& isset( $input['password_hash'] )
			&& is_string( $input['password_hash'] )
			&& hash_equals( $this->pending_password_hash, $input['password_hash'] )
		) {
			$password_hash = $this->pending_password_hash;
		}

		$output = array(
			'enabled'         => empty( $input['enabled'] ) ? 0 : 1,
			'password_hash'   => $password_hash,
			'duration_hours'  => isset( $input['duration_hours'] ) ? absint( $input['duration_hours'] ) : 168,
			'allow_logged_in' => empty( $input['allow_logged_in'] ) ? 0 : 1,
			'heading'         => isset( $input['heading'] ) ? sanitize_text_field( $input['heading'] ) : '',
			'message'         => isset( $input['message'] ) ? sanitize_textarea_field( $input['message'] ) : '',
		);

		$allowed_durations = array( 1, 8, 24, 168, 720 );
		if ( ! in_array( $output['duration_hours'], $allowed_durations, true ) ) {
			$output['duration_hours'] = 168;
		}

		if ( isset( $input['password'] ) && is_string( $input['password'] ) && '' !== $input['password'] ) {
			$password = wp_unslash( $input['password'] );

			if ( strlen( $password ) > 4096 ) {
				add_settings_error(
					self::OPTION_NAME,
					'spgate_password_too_long',
					__( 'The site password must be 4,096 characters or fewer.', 'site-password-gate' )
				);
			} else {
				$output['password_hash']     = wp_hash_password( $password );
				$this->pending_password_hash = $output['password_hash'];
			}
		}

		if ( empty( $output['heading'] ) ) {
			$output['heading'] = self::get_defaults()['heading'];
		}

		if ( empty( $output['message'] ) ) {
			$output['message'] = self::get_defaults()['message'];
		}

		if ( $output['enabled'] && empty( $output['password_hash'] ) ) {
			$output['enabled'] = 0;
			add_settings_error(
				self::OPTION_NAME,
				'spgate_password_required',
				__( 'Set a password before enabling site protection.', 'site-password-gate' )
			);
		}

		return $output;
	}

	/**
	 * Add the settings submenu.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Site Password Gate', 'site-password-gate' ),
			__( 'Site Password', 'site-password-gate' ),
			'manage_options',
			'site-password-gate',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render the settings screen.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options   = $this->get_options();
		$durations = array(
			1   => __( '1 hour', 'site-password-gate' ),
			8   => __( '8 hours', 'site-password-gate' ),
			24  => __( '1 day', 'site-password-gate' ),
			168 => __( '1 week', 'site-password-gate' ),
			720 => __( '30 days', 'site-password-gate' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Site Password Gate', 'site-password-gate' ); ?></h1>
			<p><?php esc_html_e( 'Put the public-facing site behind one shared password. Administrators and the WordPress login always remain accessible.', 'site-password-gate' ); ?></p>

			<?php settings_errors( self::OPTION_NAME ); ?>

			<form action="options.php" method="post">
				<?php settings_fields( 'spgate_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Site protection', 'site-password-gate' ); ?></th>
						<td>
							<label for="spg-enabled">
								<input id="spg-enabled" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[enabled]" type="checkbox" value="1" <?php checked( ! empty( $options['enabled'] ) ); ?>>
								<?php esc_html_e( 'Require the site password', 'site-password-gate' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Protection starts after you save a password and enable this option.', 'site-password-gate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="spg-password"><?php esc_html_e( 'Site password', 'site-password-gate' ); ?></label></th>
						<td>
							<input id="spg-password" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[password]" type="password" value="" autocomplete="new-password" spellcheck="false">
							<p class="description">
								<?php
								if ( ! empty( $options['password_hash'] ) ) {
									esc_html_e( 'A password is set. Leave this blank to keep it, or enter a new password to replace it.', 'site-password-gate' );
								} else {
									esc_html_e( 'Choose the password visitors will use. It is stored as a one-way hash.', 'site-password-gate' );
								}
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="spg-duration"><?php esc_html_e( 'Keep access for', 'site-password-gate' ); ?></label></th>
						<td>
							<select id="spg-duration" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[duration_hours]">
								<?php foreach ( $durations as $hours => $label ) : ?>
									<option value="<?php echo esc_attr( $hours ); ?>" <?php selected( (int) $options['duration_hours'], $hours ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Changing the password immediately invalidates existing access cookies.', 'site-password-gate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Logged-in visitors', 'site-password-gate' ); ?></th>
						<td>
							<label for="spg-allow-logged-in">
								<input id="spg-allow-logged-in" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[allow_logged_in]" type="checkbox" value="1" <?php checked( ! empty( $options['allow_logged_in'] ) ); ?>>
								<?php esc_html_e( 'Let logged-in WordPress users bypass the password', 'site-password-gate' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Administrators always bypass the gate to prevent lockouts.', 'site-password-gate' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="spg-heading"><?php esc_html_e( 'Screen heading', 'site-password-gate' ); ?></label></th>
						<td><input id="spg-heading" class="regular-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[heading]" type="text" value="<?php echo esc_attr( $options['heading'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="spg-message"><?php esc_html_e( 'Screen message', 'site-password-gate' ); ?></label></th>
						<td><textarea id="spg-message" class="large-text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[message]" rows="3"><?php echo esc_textarea( $options['message'] ); ?></textarea></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<p><strong><?php esc_html_e( 'Important:', 'site-password-gate' ); ?></strong> <?php esc_html_e( 'This is a simple privacy gate for staging sites, private launches, and shared previews. It is not a replacement for per-user authentication on sites containing sensitive data.', 'site-password-gate' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Add a quick Settings link on the Plugins screen.
	 *
	 * @param array<int, string> $links Existing action links.
	 * @return array<int, string>
	 */
	public function add_plugin_action_links( $links ) {
		$url           = admin_url( 'options-general.php?page=site-password-gate' );
		$settings_link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'site-password-gate' ) . '</a>';

		array_unshift( $links, $settings_link );

		return $links;
	}
}
