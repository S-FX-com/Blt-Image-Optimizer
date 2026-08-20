# BLT Image Optimizer — CLAUDE.md

## Project Overview

**Plugin Name:** BLT Image Optimizer  
**Slug:** `blt-image-optimizer`  
**Version Scheme:** Timestamp-based — `YYYY.MM.DD.HHMM`  
**Author:** S-FX.com  
**Stack:** WordPress plugin (PHP) + Cloudflare Worker (TypeScript)

A WordPress plugin that permanently optimizes images on-disk (compress + WebP conversion) by routing them through a self-hosted Cloudflare Worker. Optimizations are destructive-in-the-good-sense — results live on the server so the plugin can be disabled or handed off with zero runtime dependencies.

---

## Architecture

### Two Components

#### 1. WordPress Plugin (`/`)
PHP plugin installed on client WordPress sites. Responsible for:
- Intercepting new uploads (`wp_generate_attachment_metadata`)
- Bulk processing existing media library (Action Scheduler queue)
- Writing optimized WebP files back to disk
- Updating WP attachment metadata to reference optimized files
- Admin UI: settings, bulk runner, per-image status, logs

#### 2. Cloudflare Worker (`/worker/`)
TypeScript Worker hosted on **S-FX's Cloudflare account** — one Worker, all client sites. Responsible for:
- Accepting an image URL or binary payload via POST
- Applying `cf.image` transformations (format=webp, quality, max-width)
- Streaming optimized binary back to the plugin
- Simple shared-secret auth so only authorized WP installs can call it

### Data Flow
```
WP Upload / Bulk Run
  → Plugin POSTs image URL to CF Worker
    → Worker fetches image, applies cf.image transform
      → Worker returns optimized binary
        → Plugin writes .webp to disk (same directory as original)
          → Plugin updates attachment postmeta (sizes, file path, mime type)
            → Original optionally retained or deleted (configurable)
```

### Key Design Principle: No Runtime Dependency
Once an image is optimized, it's just a file on disk. The plugin can be deactivated, the CF Worker can go offline, and the site continues to serve optimized images indefinitely. This enables the agency hand-off model.

---

## Directory Structure

```
blt-image-optimizer/
├── CLAUDE.md                        ← this file
├── DESIGN.md                        ← BLT family design & convention standard (shared, do not fork)
├── AGENTS.md                        ← agent role definitions
├── tasks/
│   ├── todo.md                      ← active task list
│   └── lessons.md                   ← build decisions & gotchas log
├── blt-image-optimizer.php          ← plugin bootstrap, headers, init
├── uninstall.php                    ← cleanup on plugin deletion
├── includes/
│   ├── class-blt-optimizer-core.php     ← main orchestrator
│   ├── class-blt-uploader.php           ← sends image to Worker, writes result
│   ├── class-blt-attachment-meta.php    ← updates WP attachment metadata
│   ├── class-blt-queue.php              ← Action Scheduler bulk queue manager
│   ├── class-blt-settings.php           ← settings storage & retrieval
│   ├── class-blt-logger.php             ← per-image status log (custom DB table)
│   └── blt-family/                      ← shared BLT family layer (vendored, do not fork)
├── assets/
│   ├── css/blt-design-system.css        ← shared admin design system (vendored)
│   └── img/                             ← BLT mark + plugin-card icons (vendored)
├── admin/
│   ├── class-blt-admin.php              ← admin menu, page routing
│   ├── views/
│   │   ├── settings.php                 ← Worker URL, secret, quality, options
│   │   ├── bulk.php                     ← bulk optimizer UI + progress
│   │   └── log.php                      ← per-image optimization log
│   └── assets/
│       ├── blt-admin.css
│       └── blt-admin.js                 ← bulk runner AJAX polling
└── worker/
    ├── src/
    │   └── index.ts                     ← CF Worker entry point
    ├── wrangler.toml                    ← Wrangler config
    ├── package.json
    └── tsconfig.json
```

---

## WordPress Plugin Specs

### Settings (stored in `wp_options` as `blt_optimizer_settings`)
| Key | Type | Description |
|---|---|---|
| `worker_url` | string | CF Worker endpoint URL |
| `worker_secret` | string | Shared secret (encrypted at rest) |
| `webp_quality` | int (1–100) | Default 82 |
| `max_width` | int (px) | Default 2400, 0 = no limit |
| `keep_originals` | bool | Default true (keep originals alongside .webp) |
| `auto_optimize` | bool | Default true (process new uploads automatically) |
| `optimize_existing_sizes` | bool | Default true (process all WP-generated sizes, not just full) |

### Hooks Used
- `wp_generate_attachment_metadata` — intercept new uploads post-resize
- `wp_get_attachment_image_src` — rewrite URLs to .webp where file exists
- `wp_calculate_image_srcset` — rewrite srcset entries
- `wp_get_attachment_url` — rewrite single attachment URLs
- `the_content` — fallback content filter for hardcoded `<img>` tags (optional, configurable)

### Database
Custom table: `{prefix}blt_optimizer_log`
```sql
CREATE TABLE {prefix}blt_optimizer_log (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attachment_id BIGINT UNSIGNED NOT NULL,
  size_name    VARCHAR(64) NOT NULL,
  original_file VARCHAR(512),
  optimized_file VARCHAR(512),
  original_size BIGINT,
  optimized_size BIGINT,
  savings_pct  DECIMAL(5,2),
  status       ENUM('pending','processing','done','error','skipped') DEFAULT 'pending',
  error_message TEXT,
  processed_at DATETIME,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (attachment_id),
  INDEX (status)
);
```

### Bulk Queue (Action Scheduler)
- Uses Action Scheduler (bundled or standalone) — NOT WP-Cron
- Batch size: 10 images per action (configurable)
- Respects CF Worker rate limits
- Admin UI polls `/wp-admin/admin-ajax.php` for queue progress
- Supports pause/resume/cancel

---

## Cloudflare Worker Specs

### Endpoint
`POST /optimize`

### Request
```json
{
  "image_url": "https://clientsite.com/wp-content/uploads/2024/01/photo.jpg",
  "quality": 82,
  "format": "webp",
  "max_width": 2400
}
```

### Auth
`Authorization: Bearer {WORKER_SECRET}` header — validated against a Worker secret set in `wrangler.toml` vars or CF dashboard secrets.

### Response
- `200` — raw optimized image binary (`Content-Type: image/webp`)
- `400` — bad request (missing params)
- `401` — unauthorized
- `422` — image fetch failed or transform failed
- `500` — Worker error

### Worker Transform Logic
```typescript
const response = await fetch(image_url, {
  cf: {
    image: {
      format: params.format,       // 'webp'
      quality: params.quality,     // 82
      width: params.max_width > 0 ? params.max_width : undefined,
      fit: 'scale-down',
    }
  }
});
```

### Deployment
- Deployed to `img-optimizer.{sfx-subdomain}.workers.dev` or custom route
- Images Transformations must be enabled on the CF zone being used
- Worker lives on S-FX CF account — client sites never need their own CF account

---

## Plugin Lifecycle / Hand-Off Model

### Active Mode (plugin enabled)
- New uploads auto-processed
- Bulk runner available
- URL rewriting active

### Preserved Mode (plugin disabled)
- All previously optimized .webp files remain on disk
- WP attachment metadata already updated — WP natively serves .webp
- Zero CF dependency
- Plugin can be deleted without image regression

### Hand-Off Checklist (tasks/todo.md template)
- [ ] Run bulk optimizer to completion
- [ ] Verify log shows 0 errors
- [ ] Confirm savings % in log
- [ ] Disable auto_optimize in settings (or deactivate plugin)
- [ ] Document before/after file size totals for client report

---

## BLT Family Layer (shared, vendored — do not fork)

`includes/blt-family/`, `assets/css/blt-design-system.css`, `assets/img/` and
`DESIGN.md` are maintained as one canonical copy across every BLT plugin and
vendored byte-identical into each repo. Read them; never edit them here. Read
`DESIGN.md` before touching any admin screen.

What this plugin wires into it:

- **Registration** — `blt_family_register()` in `blt-image-optimizer.php`, at
  load time (not in a hook), declaring the `image_worker` group.
- **Credential fallback** — `Settings::get()` consults
  `BLT_Family::get( 'blt-image-optimizer', 'image_worker', … )` for
  `worker_url` / `worker_secret`, and only after this plugin's own option has
  come back empty. Precedence is always the plugin's own option → shared store;
  nothing ever writes a shared value back into `blt_optimizer_settings`. The
  shared read is gated on a per-plugin opt-in that defaults **off**.
- **Update policy** — the checker is built with `24` as `$checkPeriod` (a
  checker built with `0` registers no scheduler hooks at all and cannot be
  revived), then `BLT_Family_Updates::apply()` holds automatic checks to one a
  day anchored to 00:00 site time. Manual checks stay immediate, including the
  **Check for Updates** link on the Settings screen.
- **Brand** — `BLT_Family_Brand::menu_icon()` supplies the top-level menu icon
  and `BLT_Family_Brand::print_menu_icon_style()` restores dashicon-style hover
  brightening; `BLT_Family_Brand::inline_mark()` is the mark in page headers.
- **Admin UI** — every custom screen is `<div class="wrap blt-ui …">` and is
  composed from the design-system components (`.blt-card`, `.blt-field`,
  `.blt-toggle`, `.blt-badge`, `.blt-stats`, `.blt-progress`, `.blt-empty`).
  `admin/assets/blt-admin.css` carries page-specific layout only.

### "BLT" is all caps

Everywhere a human reads it: the plugin name, admin labels, page titles,
notices, docs. **Never** in machine identifiers — the text domain
`blt-image-optimizer`, the option key `blt_optimizer_settings`, the menu slug
`blt-optimizer`, the `{prefix}blt_optimizer_log` table, the
`BltImageOptimizer` namespace, `BLT_OPTIMIZER_*` constants, hook and AJAX
action names, CSS classes, and the Worker's `X-Blt-Optimizer` response header
all stay exactly as they are. Renaming any of them breaks live sites or
orphans saved data.

---

## Coding Standards

- PHP 8.0+ minimum
- All classes namespaced: `BltImageOptimizer\`
- No jQuery in admin JS — vanilla JS only
- Nonces on all AJAX endpoints
- Capability checks: `manage_options` for all admin actions
- Sanitize all inputs, escape all outputs
- Worker TypeScript: strict mode, no `any`
- Error messages logged to `blt_optimizer_log` table, surfaced in admin

---

## Known Constraints & Gotchas

- **Bricks Builder** renders some images outside standard WP filters — may need `bricks/image/attributes` filter hook or output buffer fallback
- **WooCommerce product images** generate many sizes — bulk runner batching is critical
- **Retina / 2x sizes** — WP doesn't always register these; scanner should also check for `@2x` variants on disk
- **SVG and GIF** — skip SVGs entirely; skip animated GIFs unless explicitly enabled (WebP supports animation but conversion is lossy for complex GIFs)
- **CF Images Transformations** requires the feature to be enabled on the CF zone; Worker must be on a zone with the add-on, not just workers.dev (workers.dev doesn't support `cf.image`)
- **workers.dev limitation** — `cf.image` transformations only work when the Worker is deployed to a CF zone route (not workers.dev subdomain). Worker must be deployed to a real zone.
- **File permissions** — plugin must verify write permissions to `wp-content/uploads` before queuing

---

## Related Plugins (S-FX Ecosystem)

- **BLT Gallery** — sibling plugin, R2-backed photo galleries. Separate plugin, separate purpose. Potential future shared settings layer via `BLT Core`.

---

## Version History

| Version | Date | Notes |
|---|---|---|
| 0.1.0 | TBD | Initial scaffold |
| 2026.08.20.0757 | 2026-08-20 | BLT family layer wired in (registration, `image_worker` credential fallback, daily update policy, shared design system, BLT mark); plugin renamed to **BLT Image Optimizer** in all user-facing copy |
