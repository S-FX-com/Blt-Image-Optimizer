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
