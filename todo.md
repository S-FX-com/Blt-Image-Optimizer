# tasks/todo.md — BLT Image Optimizer

_Last updated: 2026.05.30 — initial build complete_

---

## Phase 1 — Foundation

- [x] Plugin bootstrap file (`blt-image-optimizer.php`) with headers, autoloader, init
- [x] `uninstall.php` — drop custom DB table, delete options (leaves .webp + meta intact)
- [x] `class-blt-settings.php` — get/set/encrypt settings (AES-256-GCM secret)
- [x] `class-blt-logger.php` — create DB table on activation, CRUD + stats for log entries
- [x] Wrangler scaffold (`worker/wrangler.toml`, `worker/package.json`, `worker/tsconfig.json`)
- [x] Bundle plugin-update-checker (v5.7) for GitHub auto-updates

## Phase 2 — Cloudflare Worker

- [x] Worker entry point (`worker/src/index.ts`)
- [x] Auth middleware (Bearer secret validation, length-safe compare)
- [x] `cf.image` transform handler (format, quality, max_width)
- [x] Error response standardization (JSON `{error}` bodies, correct codes)
- [x] `GET /health` endpoint reporting cf.image availability (zone vs workers.dev)
- [ ] Deploy to CF zone route (NOT workers.dev — cf.image requires zone route) — _ops step_
- [x] Document Worker URL + secret in settings

## Phase 3 — Core Upload Interception

- [x] `class-blt-uploader.php` — POST to Worker, receive binary, write .webp to disk
- [x] `class-blt-attachment-meta.php` — webp size-map postmeta after optimization
- [x] Hook `wp_generate_attachment_metadata` for auto-optimize (async via Action Scheduler)
- [x] Skip logic: SVG, animated GIF (unless setting enabled), already-optimized files

## Phase 4 — URL Rewriting

- [x] Filter `wp_get_attachment_image_src`
- [x] Filter `wp_calculate_image_srcset`
- [x] Filter `wp_get_attachment_url`
- [x] Optional: `the_content` filter for hardcoded img tags (configurable)
- [x] Bricks Builder `bricks/image/attributes` hook compatibility
- [ ] Live-test Bricks/Woo rendering on a real site

## Phase 5 — Bulk Optimizer

- [x] `class-blt-queue.php` — Action Scheduler integration (with inline fallback)
- [x] Media library scanner (attachments without `_blt_optimized` meta)
- [x] Batch dispatch (10/batch, configurable)
- [x] Pause / Resume / Cancel controls
- [x] AJAX polling endpoint for progress UI

## Phase 6 — Admin UI

- [x] Admin menu registration (`class-blt-admin.php`)
- [x] Settings view — Worker URL, secret, quality, options + Test Connection
- [x] Bulk runner view — launch, progress bar, stats cards
- [x] Log view — per-image status, savings %, error messages, filters, pagination
- [x] Dashboard widget: total images optimized, total bytes saved

## Phase 7 — Hand-Off & Polish

- [x] Deactivation hook — leave files/meta intact, unschedule actions
- [ ] "Revert" option — restore originals from backup (if keep_originals = true)
- [x] Hand-off checklist display in admin (Bulk page)
- [x] README.md for the repo
- [ ] Tested on: plain WP, Bricks Builder, WooCommerce

---

## Hand-Off Checklist Template (copy per client)

- [ ] Run bulk optimizer to 100% completion
- [ ] Zero errors in log
- [ ] Record before/after totals (savings % for client report)
- [ ] Disable auto_optimize or deactivate plugin
- [ ] Confirm .webp files exist in uploads directory
- [ ] Remove plugin if full hand-off (files stay, plugin gone)
