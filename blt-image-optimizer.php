<?php
/**
 * Plugin Name:       BLT Image Optimizer
 * Plugin URI:        https://github.com/s-fx-com/blt-image-optimizer
 * Description:       Permanently optimizes images on-disk (compress + WebP conversion) by routing them through a self-hosted Cloudflare Worker. Optimized files live on the server with zero runtime dependency.
 * Version:           2026.08.20.0757
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            S-FX.com
 * Author URI:        https://www.s-fx.com
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
define( 'BLT_OPTIMIZER_VERSION', '2026.08.20.0757' );
define( 'BLT_OPTIMIZER_FILE', __FILE__ );
define( 'BLT_OPTIMIZER_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLT_OPTIMIZER_URL', plugin_dir_url( __FILE__ ) );
define( 'BLT_OPTIMIZER_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The slug the plugin-update-checker instance is built with. It is what the
 * manual "Check for Updates" request keys on, so the settings screen and the
 * checker must agree on it.
 */
define( 'BLT_OPTIMIZER_UPDATE_SLUG', 'blt-image-optimizer' );

/**
 * Shared BLT family layer — one encrypted store of connection settings for a
 * site running more than one BLT plugin, the BLT mark, and the family update
 * policy. Required (and registered) during load, before plugins_loaded, so the
 * registry is complete when the library elects a copy and boots, and so
 * BLT_Family_Updates is available where the update checker is built.
 */
require_once BLT_OPTIMIZER_DIR . 'includes/blt-family/bootstrap.php';

\blt_family_register(
	BLT_OPTIMIZER_FILE,
	array(
		'name'    => 'BLT Image Optimizer',
		'slug'    => 'blt-image-optimizer',
		'version' => BLT_OPTIMIZER_VERSION,
		'menu'    => 'blt-optimizer',
		'groups'  => array( 'image_worker' ),
	)
);

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
 * Built once and cached, so the settings screen can reach the same instance to
 * report when the last check ran.
 *
 * The 24 passed as $checkPeriod is required, not cosmetic: a checker built with
 * 0 registers no scheduler hooks at all and cannot be revived afterwards.
 * BLT_Family_Updates::apply() then holds automatic checks to one a day anchored
 * to 00:00 site time, while leaving manual checks immediate.
 *
 * @return object|null The checker instance, or null when unavailable.
 */
function update_checker() {
	static $checker = null;
	static $built   = false;

	if ( $built ) {
		return $checker;
	}

	$built = true;

	$puc = BLT_OPTIMIZER_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

	if ( ! is_readable( $puc ) ) {
		return null;
	}

	require_once $puc;

	if ( ! class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
		return null;
	}

	$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/s-fx-com/blt-image-optimizer/',
		BLT_OPTIMIZER_FILE,
		BLT_OPTIMIZER_UPDATE_SLUG,
		24
	);

	// Track tagged releases on the default branch.
	$checker->getVcsApi()->enableReleaseAssets();

	\BLT_Family_Updates::apply(
		$checker,
		array(
			'basename'  => BLT_OPTIMIZER_BASENAME,
			'icons_url' => BLT_OPTIMIZER_URL . 'assets/img/',
		)
	);

	return $checker;
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
	update_checker();

	// Core orchestrator wires up hooks for uploads and URL rewriting.
	Core::instance()->init();

	if ( is_admin() ) {
		Admin::instance()->init();
	}
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );
