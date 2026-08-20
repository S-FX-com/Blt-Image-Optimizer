# BLT Image Optimizer

A WordPress plugin by **[S-FX.com](https://www.s-fx.com)** that permanently optimizes images on-disk (compression + WebP conversion) by routing them through a self-hosted **Cloudflare Worker**.

Optimizations are destructive-in-the-good-sense: the results live on the server, so the plugin can be deactivated, the Worker can go offline, and the site keeps serving optimized images indefinitely. This enables a clean agency hand-off model with **zero runtime dependency**.

---

## How It Works

```
WP Upload / Bulk Run
  → Plugin POSTs the image URL to the CF Worker
    → Worker fetches the image, applies cf.image transform (WebP, quality, max-width)
      → Worker streams the optimized binary back
        → Plugin writes .webp to disk (same directory as the original)
          → Plugin records a webp size-map in postmeta
            → Front-end filters serve .webp wherever a file exists on disk
              → Original optionally retained (default) or deleted
```

## Components

| Path | What |
|---|---|
| `blt-image-optimizer.php` | Plugin bootstrap, autoloader, update checker, activation/deactivation |
| `includes/` | Core orchestrator, Worker client, attachment-meta, queue, settings, logger |
| `admin/` | Admin menu, settings / bulk / log views, vanilla-JS bulk runner |
| `includes/blt-family/` | Shared BLT family layer — one encrypted store of connection settings, the BLT mark, the family update policy (vendored byte-identical across BLT plugins; never fork it here) |
| `assets/` | Shared BLT admin design system (`css/blt-design-system.css`) and the BLT mark / plugin-card icons (`img/`) |
| `DESIGN.md` | The BLT family design & convention standard. Read it before touching an admin screen |
| `worker/` | TypeScript Cloudflare Worker (`cf.image` transform endpoint) |
| `vendor/plugin-update-checker/` | Bundled [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) (v5.7) for GitHub-hosted auto-updates |

---

## Installation

1. Copy this directory into `wp-content/plugins/blt-image-optimizer/`.
2. Activate **BLT Image Optimizer** in **Plugins**.
3. Go to **BLT Image Optimizer → Settings** and enter your Cloudflare Worker URL + shared secret.
4. Click **Test Connection** to confirm the Worker is reachable and `cf.image` transforms are available.
5. Run **BLT Image Optimizer → Bulk Optimizer** to process the existing media library.

> **Action Scheduler** is required for the bulk queue. It ships with WooCommerce, or install the standalone [Action Scheduler](https://actionscheduler.org/) library. Without it, new uploads are optimized inline as a fallback.

---

## Cloudflare Worker

The Worker lives on the **S-FX Cloudflare account** — one Worker serves all client sites. Client sites never need their own Cloudflare account.

### ⚠️ Critical: deploy to a zone route, not workers.dev

`cf.image` transformations **only work on a Cloudflare zone route** (e.g. `img-optimizer.s-fx.com/optimize`) with **Image Transformations enabled**. They silently fail on `*.workers.dev` subdomains. The Worker's `/health` endpoint reports `cf_image: false` when it detects a non-zone context, and the plugin's **Test Connection** surfaces this.

### Deploy

```bash
cd worker
npm install
wrangler secret put WORKER_SECRET   # set the shared bearer secret
# edit wrangler.toml → route pattern / zone_name for your zone
npm run deploy
```

### API

| Method & Path | Purpose | Auth |
|---|---|---|
| `POST /optimize` | Transform an image, return WebP binary | `Authorization: Bearer <secret>` |
| `GET /health` | Connectivity + `cf.image` availability check | `Authorization: Bearer <secret>` |

`POST /optimize` body:

```json
{
  "image_url": "https://clientsite.com/wp-content/uploads/2024/01/photo.jpg",
  "quality": 82,
  "format": "webp",
  "max_width": 2400
}
```

Responses: `200` raw `image/webp` · `400` bad request · `401` unauthorized · `422` fetch/transform failed · `404/405` routing.

---

## Settings

| Key | Default | Description |
|---|---|---|
| `worker_url` | — | CF Worker `/optimize` endpoint |
| `worker_secret` | — | Shared bearer secret (encrypted at rest) |
| `webp_quality` | 82 | 1–100 |
| `max_width` | 2400 | px; `0` = no limit |
| `keep_originals` | ✅ | Keep originals alongside `.webp` |
| `auto_optimize` | ✅ | Optimize new uploads automatically |
| `optimize_existing_sizes` | ✅ | Process all WP-generated sizes, not just full |
| `convert_gifs` | ❌ | Convert GIFs (lossy for complex animation) |
| `rewrite_content` | ❌ | Rewrite hardcoded `<img>` tags in post content |

Both Worker fields fall back to the shared `image_worker` group in the BLT
family store when this plugin's own value is empty — and only then, and only
once the site owner has opted this plugin in on the **BLT** screen (off by
default). Nothing is ever written back into `blt_optimizer_settings`.

---

## Hand-Off Model

- **Active mode** (plugin enabled): new uploads auto-processed, bulk runner available, URL rewriting active.
- **Preserved mode** (plugin disabled/removed): `.webp` files and postmeta remain; WordPress keeps serving optimized images with no Cloudflare dependency.

Uninstall drops the plugin's options and log table but **deliberately leaves `.webp` files and optimization postmeta intact**.

---

## Auto-Updates

Updates are delivered from the GitHub repository
[`s-fx-com/blt-image-optimizer`](https://github.com/s-fx-com/blt-image-optimizer)
via the bundled Plugin Update Checker. Tag a release (matching the
`YYYY.MM.DD.HHMM` version header) and WordPress will offer the update on
client sites.

The BLT family update policy applies: **at most one automatic check per day**,
anchored to **00:00 site time**. Manual checks are never delayed — use
**Check for Updates** on **BLT Image Optimizer → Settings**, the same link on the
Plugins row, or **Check again** on **Dashboard → Updates**.

---

## Requirements

- WordPress 6.0+
- PHP 8.0+
- A Cloudflare zone with Image Transformations enabled (for the Worker)
- Action Scheduler (for bulk processing)

## License

GPL-2.0-or-later · © S-FX.com
