<?php
/**
 * Plugin Name:       Blt Image Optimizer
 * Plugin URI:        https://github.com/s-fx-com/blt-image-optimizer
 * Description:        Permanently optimizes images on-disk (compress + WebP conversion) by routing them through a self-hosted Cloudflare Worker. Optimized files live on the server with zero runtime dependency.
 * Version:           2026.05.30.0001
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Shane Skwarek / S-FX.com
 * Author URI:        https://s-fx.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       blt-image-optimizer
 * Domain Path:       /languages
 *
 * @package BltImageOptimizer
 */

namespace BltImageOptimizer;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 */
define( 'BLT_OPTIMIZER_VERSION', '2026.05.30.0001' );
define( 'BLT_OPTIMIZER_FILE', __FILE__ );
define( 'BLT_OPTIMIZER_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLT_OPTIMIZER_URL', plugin_dir_url( __FILE__ ) );
define( 'BLT_OPTIMIZER_BASENAME', plugin_basename( __FILE__ ) );

/**
 * PSR-4-ish autoloader for the BltImageOptimizer namespace.
 *
 * Maps class names to includes/class-blt-{slug}.php and
 * admin/class-blt-{slug}.php following WordPress file naming.
 *
 * @param string $class Fully-qualified class name.
 * @return void
 */
spl_autoload_register(
	function ( $class ) {
		if ( 0 !== strpos( $class, __NAMESPACE__ . '\\' ) ) {
			return;
		}

		// Strip the namespace prefix and normalize the class name.
		$relative = substr( $class, strlen( __NAMESPACE__ . '\\' ) );
		$relative = strtolower( str_replace( '_', '-', $relative ) );
		$file     = 'class-blt-' . $relative . '.php';

		$candidates = array(
			BLT_OPTIMIZER_DIR . 'includes/' . $file,
			BLT_OPTIMIZER_DIR . 'admin/' . $file,
		);

		foreach ( $candidates as $path ) {
			if ( is_readable( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
);

/**
 * Bundled plugin-update-checker — enables GitHub-hosted auto-updates.
 *
 * @return void
 */
function bootstrap_update_checker() {
	$puc = BLT_OPTIMIZER_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

	if ( ! is_readable( $puc ) ) {
		return;
	}

	require_once $puc;

	if ( ! class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
		return;
	}

	$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/s-fx-com/blt-image-optimizer/',
		BLT_OPTIMIZER_FILE,
		'blt-image-optimizer'
	);

	// Track tagged releases on the default branch.
	$checker->getVcsApi()->enableReleaseAssets();
}

/**
 * Activation handler — create DB table, seed default settings, schedule.
 *
 * @return void
 */
function activate() {
	require_once BLT_OPTIMIZER_DIR . 'includes/class-blt-logger.php';
	require_once BLT_OPTIMIZER_DIR . 'includes/class-blt-settings.php';

	Logger::install();
	Settings::seed_defaults();

	flush_rewrite_rules();
}

/**
 * Deactivation handler — clear scheduled actions, leave files & meta intact.
 *
 * Preserved Mode: optimized files and attachment metadata remain so the
 * site keeps serving WebP with zero Cloudflare dependency.
 *
 * @return void
 */
function deactivate() {
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'blt_optimizer_process_batch' );
	}

	flush_rewrite_rules();
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );

/**
 * Boot the plugin once all plugins are loaded.
 *
 * @return void
 */
function init() {
	bootstrap_update_checker();

	// Core orchestrator wires up hooks for uploads and URL rewriting.
	Core::instance()->init();

	if ( is_admin() ) {
		Admin::instance()->init();
	}
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );
