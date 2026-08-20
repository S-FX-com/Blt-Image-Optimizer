<?php
/**
 * Settings view.
 *
 * @package BltImageOptimizer
 *
 * @var array  $settings    Current settings (from Settings::all()).
 * @var bool   $saved       Whether settings were just saved.
 * @var string $update_url  Nonced URL for a manual update check.
 * @var int    $last_check  Unix timestamp of the last update check (0 = never).
 */

namespace BltImageOptimizer;

defined( 'ABSPATH' ) || exit;

$has_secret = '' !== ( $settings['worker_secret'] ?? '' );
$configured = Settings::is_configured();

$toggles = array(
	'auto_optimize'           => array(
		'label' => __( 'Optimize new uploads automatically', 'blt-image-optimizer' ),
		'desc'  => __( 'Every image added to the media library is sent to the Worker as it is uploaded.', 'blt-image-optimizer' ),
	),
	'optimize_existing_sizes' => array(
		'label' => __( 'Optimize every generated size', 'blt-image-optimizer' ),
		'desc'  => __( 'Process all WordPress-generated sizes, not just the full image.', 'blt-image-optimizer' ),
	),
	'keep_originals'          => array(
		'label' => __( 'Keep original files', 'blt-image-optimizer' ),
		'desc'  => __( 'Recommended. The original stays on disk alongside the .webp.', 'blt-image-optimizer' ),
	),
	'convert_gifs'            => array(
		'label' => __( 'Convert GIFs to WebP', 'blt-image-optimizer' ),
		'desc'  => __( 'Conversion is lossy for complex animations. Off by default.', 'blt-image-optimizer' ),
	),
	'rewrite_content'         => array(
		'label' => __( 'Rewrite hardcoded image tags in post content', 'blt-image-optimizer' ),
		'desc'  => __( 'Fallback for themes and builders that render images outside the standard WordPress filters.', 'blt-image-optimizer' ),
	),
);
?>
<div class="wrap blt-ui blt-optimizer-wrap">
	<div class="blt-admin-page-header">
		<h1>
			<?php
			// Already run through wp_kses() with an explicit SVG subset inside
			// BLT_Family_Brand::inline_mark(), and echoed as-is on purpose:
			// wp_kses_post() would strip the <svg> outright, since core's
			// allowed post tags have no SVG in them.
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo \BLT_Family_Brand::inline_mark( BLT_OPTIMIZER_DIR );
			?>
			<?php esc_html_e( 'BLT Image Optimizer', 'blt-image-optimizer' ); ?>
			<span class="blt-admin-page-header-sub"><?php esc_html_e( 'Settings', 'blt-image-optimizer' ); ?></span>
		</h1>
		<div class="blt-admin-page-actions">
			<?php if ( '' !== $update_url ) : ?>
				<a class="button" href="<?php echo esc_url( $update_url ); ?>"><?php esc_html_e( 'Check for Updates', 'blt-image-optimizer' ); ?></a>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $last_check > 0 ) : ?>
		<p class="blt-admin-page-header-meta">
			<?php
			printf(
				/* translators: %s: human-readable time difference, e.g. "3 hours". */
				esc_html__( 'Last update check: %s ago. Automatic checks run once a day at midnight site time.', 'blt-image-optimizer' ),
				esc_html( human_time_diff( $last_check, time() ) )
			);
			?>
		</p>
	<?php endif; ?>

	<?php if ( ! empty( $saved ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved.', 'blt-image-optimizer' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=blt-optimizer-settings' ) ); ?>">
		<?php wp_nonce_field( 'blt_save_settings', 'blt_settings_nonce' ); ?>

		<div class="blt-card">
			<div class="blt-card-header">
				<h2><?php esc_html_e( 'Cloudflare Worker', 'blt-image-optimizer' ); ?></h2>
				<p><?php esc_html_e( 'The S-FX.com-hosted Worker that compresses and converts your images.', 'blt-image-optimizer' ); ?></p>
				<div class="blt-card-header-badges">
					<?php if ( $configured ) : ?>
						<span class="blt-badge blt-badge-on"><?php esc_html_e( 'Connected', 'blt-image-optimizer' ); ?></span>
					<?php else : ?>
						<span class="blt-badge blt-badge-off"><?php esc_html_e( 'Not connected', 'blt-image-optimizer' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			<div class="blt-card-body">
				<div class="blt-field">
					<div class="blt-field-label">
						<label for="blt_worker_url"><?php esc_html_e( 'Worker URL', 'blt-image-optimizer' ); ?></label>
					</div>
					<div>
						<input type="url" class="regular-text code" id="blt_worker_url"
							name="blt_settings[worker_url]"
							value="<?php echo esc_attr( $settings['worker_url'] ); ?>"
							placeholder="https://img-optimizer.s-fx.com/optimize" />
						<p class="blt-field-desc">
							<?php esc_html_e( 'The /optimize endpoint of your Cloudflare Worker. Must be deployed to a Cloudflare zone route — cf.image transforms do NOT work on workers.dev subdomains.', 'blt-image-optimizer' ); ?>
						</p>
					</div>
				</div>
				<div class="blt-field">
					<div class="blt-field-label">
						<label for="blt_worker_secret"><?php esc_html_e( 'Worker Secret', 'blt-image-optimizer' ); ?></label>
					</div>
					<div>
						<input type="password" class="regular-text code" id="blt_worker_secret"
							name="blt_settings[worker_secret]" autocomplete="new-password"
							placeholder="<?php echo $has_secret ? esc_attr__( '•••••••• (leave blank to keep current)', 'blt-image-optimizer' ) : ''; ?>" />
						<p class="blt-field-desc">
							<?php esc_html_e( 'Shared bearer secret. Sent as Authorization: Bearer header. Stored encrypted at rest. Leave blank to keep the current secret.', 'blt-image-optimizer' ); ?>
						</p>
						<?php if ( $has_secret ) : ?>
							<p class="blt-field-desc">
								<label>
									<input type="checkbox" name="blt_settings[worker_secret_clear]" value="1" />
									<?php esc_html_e( 'Clear the saved secret', 'blt-image-optimizer' ); ?>
								</label>
								<br />
								<?php esc_html_e( 'Only needed to hand this over to a shared BLT credential: this plugin\'s own secret always wins, so the shared one applies only once nothing is stored here.', 'blt-image-optimizer' ); ?>
							</p>
						<?php endif; ?>
						<p class="blt-stack-top">
							<button type="button" class="button" id="blt-test-connection"><?php esc_html_e( 'Test Connection', 'blt-image-optimizer' ); ?></button>
							<span id="blt-test-result" class="blt-test-result"></span>
						</p>
					</div>
				</div>
			</div>
		</div>

		<div class="blt-card">
			<div class="blt-card-header">
				<h2><?php esc_html_e( 'Optimization', 'blt-image-optimizer' ); ?></h2>
				<p><?php esc_html_e( 'How aggressively images are compressed and how the bulk run is paced.', 'blt-image-optimizer' ); ?></p>
			</div>
			<div class="blt-card-body">
				<div class="blt-field">
					<div class="blt-field-label">
						<label for="blt_webp_quality"><?php esc_html_e( 'WebP Quality', 'blt-image-optimizer' ); ?></label>
					</div>
					<div>
						<input type="number" min="1" max="100" id="blt_webp_quality"
							name="blt_settings[webp_quality]"
							value="<?php echo esc_attr( $settings['webp_quality'] ); ?>" />
						<p class="blt-field-desc"><?php esc_html_e( '1–100. Default 82.', 'blt-image-optimizer' ); ?></p>
					</div>
				</div>
				<div class="blt-field">
					<div class="blt-field-label">
						<label for="blt_max_width"><?php esc_html_e( 'Max Width (px)', 'blt-image-optimizer' ); ?></label>
					</div>
					<div>
						<input type="number" min="0" id="blt_max_width"
							name="blt_settings[max_width]"
							value="<?php echo esc_attr( $settings['max_width'] ); ?>" />
						<p class="blt-field-desc"><?php esc_html_e( 'Images wider than this are scaled down. 0 = no limit. Default 2400.', 'blt-image-optimizer' ); ?></p>
					</div>
				</div>
				<div class="blt-field">
					<div class="blt-field-label">
						<label for="blt_batch_size"><?php esc_html_e( 'Batch Size', 'blt-image-optimizer' ); ?></label>
					</div>
					<div>
						<input type="number" min="1" max="100" id="blt_batch_size"
							name="blt_settings[batch_size]"
							value="<?php echo esc_attr( $settings['batch_size'] ); ?>" />
						<p class="blt-field-desc"><?php esc_html_e( 'Attachments processed per bulk batch. Default 10.', 'blt-image-optimizer' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<div class="blt-card">
			<div class="blt-card-header">
				<h2><?php esc_html_e( 'Behavior', 'blt-image-optimizer' ); ?></h2>
				<p><?php esc_html_e( 'What the plugin does on upload, and what it leaves on disk.', 'blt-image-optimizer' ); ?></p>
			</div>
			<div class="blt-card-body">
				<div class="blt-toggle-stack">
					<?php foreach ( $toggles as $key => $toggle ) : ?>
						<label class="blt-toggle">
							<input type="checkbox" name="blt_settings[<?php echo esc_attr( $key ); ?>]" value="1"
								<?php checked( ! empty( $settings[ $key ] ) ); ?> />
							<span class="blt-toggle-track" aria-hidden="true"><span class="blt-toggle-thumb"></span></span>
							<span class="blt-toggle-text">
								<span class="blt-toggle-label"><?php echo esc_html( $toggle['label'] ); ?></span>
								<span class="blt-toggle-desc"><?php echo esc_html( $toggle['desc'] ); ?></span>
							</span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<div class="blt-settings-footer">
			<button type="submit" class="button button-primary blt-save-button" name="blt_settings_submit" value="1">
				<?php esc_html_e( 'Save Settings', 'blt-image-optimizer' ); ?>
			</button>
		</div>
	</form>
</div>
