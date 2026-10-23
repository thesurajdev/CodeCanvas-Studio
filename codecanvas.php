<?php
/**
 * Plugin Name: CodeCanvas
 * Plugin URI: https://webot.surajdev.com/
 * Description: A code-first WordPress builder with a real-file Code Studio, a scoped MCP server, coded pages and a generated website theme.
 * Version: 2.0.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Author: Suraj Dev
 * Author URI: https://surajdev.com/
 * License: GNU General Public License v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: codecanvas
 * Domain Path: /languages
 *
 * @package CodeCanvas
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CODECANVAS_VERSION', '2.0.0' );
define( 'CODECANVAS_FILE', __FILE__ );
define( 'CODECANVAS_BASENAME', plugin_basename( __FILE__ ) );
define( 'CODECANVAS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CODECANVAS_URI', plugin_dir_url( __FILE__ ) );

require_once CODECANVAS_DIR . 'includes/setup.php';
require_once CODECANVAS_DIR . 'includes/views.php';
require_once CODECANVAS_DIR . 'includes/security.php';
require_once CODECANVAS_DIR . 'includes/assets.php';
require_once CODECANVAS_DIR . 'includes/admin.php';
require_once CODECANVAS_DIR . 'includes/page-editor.php';
require_once CODECANVAS_DIR . 'includes/dev-studio.php';
require_once CODECANVAS_DIR . 'includes/mcp.php';
require_once CODECANVAS_DIR . 'includes/mcp-tools.php';
require_once CODECANVAS_DIR . 'includes/mcp-oauth.php';
require_once CODECANVAS_DIR . 'includes/mcp-settings.php';
require_once CODECANVAS_DIR . 'includes/mcp-safety.php';

/** Flush rewrite rules and queue the welcome redirect on activation. */
function codecanvas_activate() {
    flush_rewrite_rules();
    set_transient( 'codecanvas_welcome_redirect', 1, 90 );
}

/** Remove CodeCanvas rewrite rules when the plugin is deactivated. */
function codecanvas_deactivate() {
    flush_rewrite_rules();
}

register_activation_hook( __FILE__, 'codecanvas_activate' );
register_deactivation_hook( __FILE__, 'codecanvas_deactivate' );
