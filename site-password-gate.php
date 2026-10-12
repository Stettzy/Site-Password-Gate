<?php
/**
 * Plugin Name:       Site Password Gate
 * Description:       Protect an entire WordPress site with one shared password and a clean, accessible unlock screen.
 * Version:           1.0.1
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Stettzy
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       site-password-gate
 *
 * @package SitePasswordGate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SPGATE_VERSION', '1.0.1' );
define( 'SPGATE_PLUGIN_FILE', __FILE__ );
define( 'SPGATE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPGATE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SPGATE_PLUGIN_DIR . 'includes/class-spgate-plugin.php';

register_activation_hook( __FILE__, array( 'SPGATE_Plugin', 'activate' ) );

SPGATE_Plugin::instance();
