# tasks/lessons.md — Blt Image Optimizer

Decisions made, gotchas hit, patterns established. Update this as the build progresses.

---

## 2026.05.30 — Initial Scaffold

**cf.image requires a CF zone route, not workers.dev**
`cf.image` transformations are only available when a Worker is deployed to a Cloudflare zone route (e.g. `img-optimizer.s-fx.com/optimize`). Deploying to `{name}.workers.dev` will silently fail or return the original unmodified image. Always deploy to a zone. Document this prominently in setup instructions.

**Action Scheduler over WP-Cron**
WP-Cron is unreliable on managed hosts (Rocket.net, Kinsta, etc.) because it only fires on page load. Action Scheduler (bundled with WooCommerce or installable standalone) has its own runner and handles large queues reliably. Use it for the bulk optimizer queue. Past lesson: FluentCRM import hang on Rocket.net was WP-Cron dependency.

**Keep originals by default**
Default `keep_originals = true`. Losing originals is unrecoverable. If a client upgrades their plan, changes CDN, or wants to re-process at higher quality, originals are the source of truth. Disk space is cheap.

**Bricks Builder image hooks**
Bricks does not always pass through `wp_get_attachment_image_src`. Also hook `bricks/image/attributes` to rewrite src/srcset on Bricks-rendered images. May need output buffer as a last resort fallback — implement this only if the targeted hooks miss cases in testing.

**SVG: skip entirely**
SVGs are already vector/compressed. Running them through the Worker will fail or return garbage. Add a mime type check (`image/svg+xml`) and skip before dispatching to the Worker.

**Animated GIFs: skip by default**
WebP supports animation, but CF's `cf.image` transform of complex animated GIFs can be lossy or drop frames. Default to skip. Add an opt-in setting for users who knowingly want to convert them.

**Workers.dev limitation on cf.image (critical)**
See above. This is the #1 setup mistake. Add a validation check in the Settings UI that pings the Worker and returns an error if `cf.image` appears unsupported (Worker can return a diagnostic header indicating zone vs. workers.dev context).

---

## 2026.05.30 — Initial Build

**Parallel webp size-map instead of mutating core metadata**
Rather than rewriting `_wp_attachment_metadata`'s `file` fields to point at `.webp`
(which can break other plugins that read canonical metadata, and complicates revert),
the plugin stores a parallel `_blt_webp_sizes` postmeta map and lets the front-end
rewrite filters swap extensions only when a `.webp` exists on disk. The `_blt_optimized`
flag marks completion and drives the bulk scanner's "not yet optimized" query.

**Auto-optimize is async**
`wp_generate_attachment_metadata` enqueues an Action Scheduler async action
(`blt_optimizer_process_single`) instead of doing the Worker round-trip inline — uploads
stay fast and a slow/unreachable Worker never blocks the editor. Falls back to inline
optimization only when Action Scheduler is absent.

**Secret encrypted at rest with AES-256-GCM**
`worker_secret` is encrypted using a key derived from WP auth salts (sha256), with an
openssl GCM path and a clearly-tagged base64 fallback. Empty secret field on save
preserves the existing value so the field can render masked.

**Worker /health reports cf.image context**
The Worker exposes `GET /health` returning `cf_image: <bool>` based on the presence of
`request.cf` (populated on zone routes, absent on workers.dev). Settings → Test Connection
surfaces this so the #1 setup mistake is caught before any bulk run.

**Plugin Update Checker bundled, not Composer**
PUC v5.7 is vendored under `vendor/plugin-update-checker/` and loaded directly (no Composer
autoload on client installs). Pointed at `s-fx-com/blt-image-optimizer` with release assets
enabled — tag a release matching the `YYYY.MM.DD.HHMM` header to ship an update.
