# AGENTS.md — Blt Image Optimizer

## Agent Roles

### wordpress-plugin-agent
Responsible for all PHP plugin code under `/` and `/includes/` and `/admin/`.
- Follows WordPress coding standards and `BltImageOptimizer\` namespace
- Owns all WP hooks, attachment metadata logic, admin UI, AJAX handlers
- Must read `tasks/lessons.md` before writing any hook-related code
- Never writes Worker code

### cloudflare-worker-agent
Responsible for all code under `/worker/`.
- TypeScript strict mode
- Owns Worker fetch handler, `cf.image` transform logic, auth validation
- Must confirm CF zone has Images Transformations enabled before assuming `cf.image` works
- Never writes PHP code

### qa-agent
- Reviews output of both agents
- Checks: nonces present, capabilities checked, inputs sanitized, outputs escaped
- Validates Worker auth header is checked before any processing
- Flags any `cf.image` usage on workers.dev routes (not supported)

### scaffold-agent
- Generates boilerplate: plugin headers, class stubs, wrangler.toml, package.json
- Runs once at project init; subsequent changes go to the relevant specialist agent
