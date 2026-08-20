<?php
/**
 * Uninstall cleanup for BLT Image Optimizer.
 *
 * Removes the plugin's options and custom log table. Optimized .webp files
 * and attachment postmeta are intentionally LEFT INTACT — the hand-off model
 * means the site continues serving optimized images after the plugin is gone.
 *
 * @package BltImageOptimizer
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Drop the custom log table.
$table = $wpdb->prefix . 'blt_optimizer_log';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

// Delete plugin options.
$options = array(
	'blt_optimizer_settings',
	'blt_optimizer_db_version',
	'blt_optimizer_queue_state',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// NOTE: We deliberately do NOT delete _blt_webp_sizes / _blt_optimized postmeta
// or any .webp files on disk. Those are the durable result of optimization and
// must survive plugin removal (Preserved / Hand-Off Mode).
