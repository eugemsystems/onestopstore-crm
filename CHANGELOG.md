# Changelog — onestopstore-crm

All notable changes to this folder are recorded here, newest first.
Update this file in the same commit as every change.

## Tagging convention
- Semantic versioning tags `vMAJOR.MINOR.PATCH` (patch: fixes, minor: features, major: breaking). Versions start at 1.
- After a release-worthy commit: `git tag -a vX.Y.Z -m "summary" && git push origin vX.Y.Z`.
- `v1.0.0` is the first tag: tracking starts here. Earlier history is in `git log`.

---

## [v1.0.0] — 2026-11-23

### Added
- **Delivery options on order items** (ported from raines laravel-crm v1.0.1): the option the customer chose (e.g. "Fastest Delivery" / "Best Price"), its delivery time and frozen price are stored on `order_products` and shown wherever order items are listed.
  - Migration `2026_11_23_100000_add_delivery_option_to_order_products` adds `delivery_option_id/label/text/price`. Run it BEFORE the API starts sending the new fields.
  - `UpsertOrderFromWebhook` stores the fields and mixes the option id into the composite item id, so two options of one product on the same order stay separate rows (items without an option keep their existing ids).
  - Item-status updates (CRM -> API and API -> CRM) carry `delivery_option_id` so the right line is matched.
  - Shown in: items list, order item card, orders drawer, order-items drawer, item status modal and the order-items-updated customer email.

### Fixed
- Pages loaded without CSS/images behind the https reverse proxy: the app did not trust the proxy's `X-Forwarded-Proto`, so `asset()` produced `http://` links. `bootstrap/app.php` now calls `trustProxies(at: '*')`.
- Sidebar menu was empty right after logging in until a manual refresh: the login redirected with Livewire `wire:navigate`; it now uses a normal full page load. (`resources/views/livewire/pages/auth/login.blade.php`)
- Known limit: `orders:backfill` uses its own id scheme for pivot-shaped payloads and does not separate options; use the API's `orders:sync-to-crm` to resync.
