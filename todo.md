# tasks/todo.md — Blt Image Optimizer

_Last updated: 2026.05.30_

---

## Phase 1 — Foundation

- [ ] Plugin bootstrap file (`blt-image-optimizer.php`) with headers, autoloader, init
- [ ] `uninstall.php` — drop custom DB table, delete options
- [ ] `class-blt-settings.php` — get/set/encrypt settings
- [ ] `class-blt-logger.php` — create DB table on activation, CRUD for log entries
- [ ] Wrangler scaffold (`worker/wrangler.toml`, `worker/package.json`, `worker/tsconfig.json`)

## Phase 2 — Cloudflare Worker

- [ ] Worker entry point (`worker/src/index.ts`)
- [ ] Auth middleware (Bearer secret validation)
- [ ] `cf.image` transform handler (format, quality, max_width)
- [ ] Error response standardization
- [ ] Deploy to CF zone route (NOT workers.dev — cf.image requires zone route)
- [ ] Document Worker URL + secret in settings

## Phase 3 — Core Upload Interception

- [ ] `class-blt-uploader.php` — POST to Worker, receive binary, write .webp to disk
- [ ] `class-blt-attachment-meta.php` — update attachment postmeta after optimization
- [ ] Hook `wp_generate_attachment_metadata` for auto-optimize on new uploads
- [ ] Skip logic: SVG, animated GIF (unless setting enabled), already-optimized files

## Phase 4 — URL Rewriting

- [ ] Filter `wp_get_attachment_image_src`
- [ ] Filter `wp_calculate_image_srcset`
- [ ] Filter `wp_get_attachment_url`
- [ ] Optional: `the_content` filter for hardcoded img tags
- [ ] Test: Bricks Builder `bricks/image/attributes` hook compatibility

## Phase 5 — Bulk Optimizer

- [ ] `class-blt-queue.php` — Action Scheduler integration
- [ ] Media library scanner (get all attachments not yet optimized)
- [ ] Batch dispatch (10/batch, configurable)
- [ ] Pause / Resume / Cancel controls
- [ ] AJAX polling endpoint for progress UI

## Phase 6 — Admin UI

- [ ] Admin menu registration (`class-blt-admin.php`)
- [ ] Settings view — Worker URL, secret, quality, options
- [ ] Bulk runner view — launch, progress bar, stats
- [ ] Log view — per-image status, savings %, error messages
- [ ] Dashboard widget: total images optimized, total bytes saved

## Phase 7 — Hand-Off & Polish

- [ ] Deactivation hook — leave files/meta intact, show reminder to run bulk first
- [ ] "Revert" option — restore originals from backup (if keep_originals = true)
- [ ] Hand-off checklist display in admin (pre-deactivation confirmation modal)
- [ ] README.md for the repo
- [ ] Tested on: plain WP, Bricks Builder, WooCommerce

---

## Hand-Off Checklist Template (copy per client)

- [ ] Run bulk optimizer to 100% completion
- [ ] Zero errors in log
- [ ] Record before/after totals (savings % for client report)
- [ ] Disable auto_optimize or deactivate plugin
- [ ] Confirm .webp files exist in uploads directory
- [ ] Remove plugin if full hand-off (files stay, plugin gone)
