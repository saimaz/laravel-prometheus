# Changelog

All notable changes to `laravel-prometheus` will be documented in this file.

## 2.3.0 - 2026-07-28

### Added

- **`prometheus:prune-labels --match='<glob>'`** — deletes stored series whose
  label values match a glob, with `--dry-run` to preview. Redis retains every
  series it has ever seen and re-exports it on each scrape, so the 2.2.0 label
  fixes stop new junk but cannot clear what is already stored. Pruning is
  surgical: only matching series are removed, so unrelated gauges (import
  freshness, MRR) that are refreshed only by an infrequent job keep their
  values — unlike a full storage wipe. Requires the `redis` driver.

### Changed

- CI now runs a Redis service, and the prune suite fails instead of skipping
  when Redis is unreachable under `CI=true`.

## 2.2.0 - 2026-07-27

Two label-quality fixes. Both change the label **values** you already have in
Prometheus, so existing series are replaced by new (correct) ones — dashboards
keep working, historical series age out with retention.

### Fixed

- **`route` label no longer leaks Laravel's `generated::{random}` names.**
  `route:cache` assigns `generated::` + a random string to every unnamed route,
  re-rolled on each build, so each deploy created a fresh, meaningless set of
  series. Such names are now treated as "unnamed" and fall back to the stable
  URI pattern (`app/invoices/{invoice}`). Real-world impact before the fix:
  87% of one app's route series were `generated::` noise.
- **`command` label on scheduler metrics no longer collapses to `php'`.**
  `Application::formatCommandString()` escapes the php/artisan paths via
  `ProcessUtils`, producing `'/usr/bin/php' 'artisan' foo:bar`. The extraction
  regex did not tolerate those quotes, so every scheduled task fell through to
  the PHP binary's basename and shared one useless series. Quoting styles
  (single, double, bare, absolute paths) are now all handled, and the
  non-artisan fallback strips quotes too.

## 1.0.0 - 2026-04-05

- Initial release
- Zero-config Prometheus metrics for Laravel
- Automatic HTTP request tracking (counter + histogram)
- Automatic Horizon queue metrics when `laravel/horizon` is installed
- `/metrics` endpoint with IP whitelisting
- Redis, APC, and in-memory storage adapters
- Custom metric definitions via PHP backed enums
- Extensible collector system
- Custom HTTP label providers

