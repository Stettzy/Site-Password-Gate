<?php
/**
 * Lightweight behavioral tests that do not require a WordPress installation.
 *
 * These stubs intentionally cover only the WordPress APIs exercised below.
 * Integration behavior is verified separately with Plugin Check/WordPress.
 *
 * @package KnockFirstPasswordProtection
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'SPGATE_PLUGIN_FILE', dirname( __DIR__ ) . '/knock-first-password-protection.php' );
define( 'SPGATE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'SPGATE_PLUGIN_URL', 'https://example.test/wp-content/plugins/knock-first-password-protection/' );
define( 'SPGATE_VERSION', '1.0.2' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'COOKIEHASH', 'testhash' );
define( 'COOKIEPATH', '/subsite/' );

$GLOBALS['spg_test_options']         = array();
$GLOBALS['spg_test_settings_errors'] = array();
$GLOBALS['spg_test_logged_in']       = false;
$GLOBALS['spg_test_can_manage']      = false;

function __( $text, $domain = 'default' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return $text;
}

function add_action() {}
function add_filter() {}
function is_admin() {
	return false;
}
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['spg_test_options'] ) ? $GLOBALS['spg_test_options'][ $name ] : $default;
}
function add_option( $name, $value ) {
	if ( ! array_key_exists( $name, $GLOBALS['spg_test_options'] ) ) {
		$GLOBALS['spg_test_options'][ $name ] = $value;
		return true;
	}

	return false;
}
function update_option( $name, $value ) {
	$GLOBALS['spg_test_options'][ $name ] = $value;
	return true;
}
function delete_option( $name ) {
	if ( array_key_exists( $name, $GLOBALS['spg_test_options'] ) ) {
		unset( $GLOBALS['spg_test_options'][ $name ] );
		return true;
	}

	return false;
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, $args );
}
function apply_filters( $hook, $value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	return $value;
}
function wp_doing_ajax() {
	return false;
}
function wp_doing_cron() {
	return false;
}
function is_user_logged_in() {
	return $GLOBALS['spg_test_logged_in'];
}
function current_user_can() {
	return $GLOBALS['spg_test_can_manage'];
}
function wp_check_password( $password, $hash ) {
	return password_verify( $password, $hash );
}
function wp_hash_password( $password ) {
	return password_hash( $password, PASSWORD_BCRYPT );
}
function absint( $value ) {
	return abs( (int) $value );
}
function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}
function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}
function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}
function sanitize_textarea_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}
function add_settings_error( $setting, $code, $message ) {
	$GLOBALS['spg_test_settings_errors'][] = compact( 'setting', 'code', 'message' );
}
function home_url( $path = '' ) {
	$home = 'https://example.test/subsite';
	return '' === $path ? $home : rtrim( $home, '/' ) . '/' . ltrim( $path, '/' );
}
function wp_salt() {
	return 'unit-test-salt';
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}
function trailingslashit( $value ) {
	return rtrim( $value, '/' ) . '/';
}
function esc_url_raw( $url ) {
	return filter_var( $url, FILTER_SANITIZE_URL );
}
function wp_validate_redirect( $location, $fallback = '' ) {
	$host      = wp_parse_url( $location, PHP_URL_HOST );
	$home_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	return $host === $home_host ? $location : $fallback;
}
function register_activation_hook() {}
function plugin_basename( $file ) {
	return basename( $file );
}

class WP_Error {
	public $code;
	public $message;
	public $data;

	public function __construct( $code, $message, $data = array() ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-spgate-plugin.php';

/**
 * Fail loudly when a condition is false.
 *
 * @param bool   $condition Assertion result.
 * @param string $message   Failure message.
 * @return void
 */
function spg_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
}

/**
 * Invoke a private method for focused unit testing.
 *
 * @param object $object    Target object.
 * @param string $method    Method name.
 * @param array  $arguments Method arguments.
 * @return mixed
 */
function spg_call_private( $object, $method, $arguments = array() ) {
	$reflection = new ReflectionMethod( $object, $method );
	$reflection->setAccessible( true );
	return $reflection->invokeArgs( $object, $arguments );
}

$plugin = SPGATE_Plugin::instance();

SPGATE_Plugin::activate();
$defaults = $GLOBALS['spg_test_options']['spgate_options'];
spg_assert( 0 === $defaults['enabled'], 'Protection must be disabled on activation.' );
spg_assert( '' === $defaults['password_hash'], 'Activation must not create a password.' );

$saved = $plugin->sanitize_options(
	array(
		'enabled'         => '1',
		'password'        => 'correct horse battery staple',
		'duration_hours'  => '24',
		'allow_logged_in' => '1',
		'heading'         => 'Client preview',
		'message'         => 'Use the password we sent you.',
	)
);
spg_assert( 1 === $saved['enabled'], 'A valid password should allow protection to be enabled.' );
spg_assert( password_verify( 'correct horse battery staple', $saved['password_hash'] ), 'The submitted password must be stored as a hash.' );
spg_assert( 'correct horse battery staple' !== $saved['password_hash'], 'The plain-text password must never be stored.' );
spg_assert( 24 === $saved['duration_hours'], 'An allowed access duration should be retained.' );

// WordPress can run a sanitizer twice when adding an option for the first time.
unset( $GLOBALS['spg_test_options']['spgate_options'] );
$first_pass = $plugin->sanitize_options(
	array(
		'enabled'         => '1',
		'password'        => 'first save password',
		'duration_hours'  => '24',
		'allow_logged_in' => '1',
		'heading'         => 'First save',
		'message'         => 'First save test.',
	)
);
$second_pass = $plugin->sanitize_options( $first_pass );
spg_assert( 1 === $second_pass['enabled'], 'A second sanitization pass must not disable protection.' );
spg_assert( password_verify( 'first save password', $second_pass['password_hash'] ), 'A second sanitization pass must retain the new password hash.' );

$GLOBALS['spg_test_options'] = array( 'spg_options' => $saved );
$plugin->ensure_options_exist();
spg_assert( isset( $GLOBALS['spg_test_options']['spgate_options'] ), 'The legacy option must migrate to the current key.' );
spg_assert( ! isset( $GLOBALS['spg_test_options']['spg_options'] ), 'The legacy option must be removed after migration.' );

$GLOBALS['spg_test_options']['spgate_options'] = $saved;
spg_assert( spg_call_private( $plugin, 'password_is_valid', array( 'correct horse battery staple' ) ), 'The correct password should validate.' );
spg_assert( ! spg_call_private( $plugin, 'password_is_valid', array( 'incorrect' ) ), 'An incorrect password must be rejected.' );

$expires     = time() + HOUR_IN_SECONDS;
$signature   = spg_call_private( $plugin, 'sign_expiration', array( $expires, $saved['password_hash'] ) );
$cookie_name = spg_call_private( $plugin, 'get_cookie_name' );
$_COOKIE[ $cookie_name ] = $expires . '|' . $signature;
spg_assert( spg_call_private( $plugin, 'has_valid_access_cookie', array( $saved ) ), 'A correctly signed, unexpired access cookie should validate.' );

$changed                  = $saved;
$changed['password_hash'] = wp_hash_password( 'new password' );
spg_assert( ! spg_call_private( $plugin, 'has_valid_access_cookie', array( $changed ) ), 'Changing the password must invalidate old access cookies.' );

$_COOKIE[ $cookie_name ] = ( time() - 10 ) . '|' . $signature;
spg_assert( ! spg_call_private( $plugin, 'has_valid_access_cookie', array( $saved ) ), 'Expired access cookies must be rejected.' );

unset( $_COOKIE[ $cookie_name ] );
$GLOBALS['spg_test_logged_in']  = true;
$GLOBALS['spg_test_can_manage'] = true;
spg_assert( spg_call_private( $plugin, 'has_access' ), 'Administrators must always bypass the gate.' );

$GLOBALS['spg_test_can_manage']      = false;
$saved['allow_logged_in']            = 0;
$GLOBALS['spg_test_options']['spgate_options'] = $saved;
spg_assert( ! spg_call_private( $plugin, 'has_access' ), 'Other logged-in users should respect the bypass setting.' );

$GLOBALS['spg_test_logged_in'] = false;
$_SERVER['REQUEST_URI']        = '/subsite/private%20page?from=email';
$current_url                   = spg_call_private( $plugin, 'get_current_url' );
spg_assert( 'https://example.test/subsite/private%20page?from=email' === $current_url, 'Redirect URLs must work for subdirectory installations.' );

$rest_error = $plugin->protect_rest_api( null );
spg_assert( $rest_error instanceof WP_Error && 401 === $rest_error->data['status'], 'Anonymous REST requests must be denied while protected.' );
spg_assert( "User-agent: *\nDisallow: /\n" === $plugin->filter_robots_txt( '', true ), 'Protected sites must disallow robots.' );
spg_assert( false === $plugin->filter_sitemaps_enabled( true ), 'Core sitemaps must be disabled while protected.' );

$GLOBALS['spg_test_options']['spgate_options'] = SPGATE_Plugin::get_defaults();
$GLOBALS['spg_test_settings_errors']        = array();
$invalid = $plugin->sanitize_options(
	array(
		'enabled'        => '1',
		'duration_hours' => '999',
	)
);
spg_assert( 0 === $invalid['enabled'], 'Protection cannot be enabled without a password.' );
spg_assert( 168 === $invalid['duration_hours'], 'Unknown access durations must fall back to one week.' );
spg_assert( ! empty( $GLOBALS['spg_test_settings_errors'] ), 'Invalid settings must show an administrator-facing error.' );

fwrite( STDOUT, "Knock First behavioral tests passed.\n" );
