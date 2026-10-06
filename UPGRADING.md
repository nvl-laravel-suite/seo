# Upgrading NVL SEO

## Optional tenancy adoption

Deploy nullable tenant columns before the tenancy flag is enabled. Under
maintenance, provide reviewed redirect/profile mappings, backfill and recompute
tenant-inclusive source hashes, verify canonical owner and translation
equality, then activate tenant-leading uniqueness and non-null constraints.
Delete old global sitemap namespaces only after the captured version cutover.
Drain old jobs and restart workers so no retained source or cache object can
write with pre-adoption identity. The flag is not reversible after activation.

Outside HTTP, install a host-verified `TenantSiteContext` while inside
`TenantRunner`. Configured sitemap sources must implement
`TenantSafeSitemapSource`; object instances are rejected in adopted mode.

## Unreleased hardening

Run the new `create_seo_redirect_locks_table` migration before redirect writes.
It adds a stable database mutex that keeps loop validation and graph updates
serialized through the enclosing transaction. Installations maintaining copied
migrations should copy this new migration through their existing migration
ownership workflow; do not enable both vendor and copied migration sources.

Owner packages may now register exclusive SEO sitemap ownership. Pages uses
this to enforce its publication state and SEO exclusions together. Other
profile owners retain the existing standalone sitemap behavior.

## Upgrading to 1.0

Version 1.0 is headless, uses dedicated translation rows, and has no application-specific importer.

1. Set `seo.migrations.enabled=false` for an existing adopted schema. Clean
   package migrations now fail loudly if their tables already exist instead of
   silently recording a migration that rollback could later destroy.
2. Run `php artisan nvl:seo:doctor --strict --format=json`.
3. Implement `SeoImportSource` in an application-owned bridge and import pages in stable batches.
4. Register unique owner aliases and bind `SeoAuthorization`. HTTP/import
   payloads now use `ownerAlias`; raw `ownerType` inputs were removed.
5. Supply revision `0` for profile/redirect creates and exact positive
   revisions for subsequent management writes.
6. Resolve path conflicts before enabling profiles.
7. Enable management, sitemap, robots, and redirect routes independently.
8. Choose `seo.structured_data.mode`; register resource providers for generated
   schemas and retain persisted nodes only for editor-owned exceptions.
9. Replace `seo.management.prefix` with `seo.management.path`, configure
   `seo.management.name`, and configure `seo.routes.name` for public routes.
10. Use an atomic-lock-capable cache store when sitemap caching is enabled,
    configure a durable private sitemap artifact disk/directory, and set both
    URL and uncompressed-byte limits.
11. Run the query-index hardening migration. It replaces write-heavy legacy
    indexes with the composite profile sitemap scan index expected by the
    doctor.
12. Treat the `seo.profiles` centralized translation resource as discoverable
    and reportable but read-only. All mutations must use
    `SyncSeoProfileAction`.

Verify localized paths, canonical and hreflang output, redirects, sitemap artifacts, row counts, and rollback before removing old SEO code.

## Shared owner registry compatibility

Move model identity declarations to `nvl-core.owners` and reference the alias from `seo` capability configuration as described in the [README](README.md#shared-owner-identity). Preserve package contracts, resolvers/handlers, visibility scopes, and mutation authorization. Core does not grant package capabilities. Conflicting aliases or multiple canonical aliases for one model fail before use.

Legacy class inputs remain accepted for one major cycle and are reported through Core diagnostics. Existing Content, Taxonomy, and Metafields mappings keep their established aliases. Legacy SEO, Media, Comments, Templates, Pages, and Translatable class-backed behavior does not automatically create a new morph alias. Existing host morph mappings are respected.

Adding a canonical Core alias changes Laravel's write-time morph type for that model. Before adding it to an existing class-backed deployment, explicitly convert the known package-owned morph columns and reconcile every other affected host relationship. Keep unrelated rows and host-owned morph tables unchanged. This release performs no automatic owner-data conversion and does not ship `nvl:owners:upgrade`. Preserve existing aliases when no conversion is required, rebuild configuration caches, and restart workers after the cutover.

## Shared Doctor integration

The loaded package provider now contributes its existing inspection checks to Core's `nvl:doctor --strict --format=json`. The package command remains available. The shared gate fails errors and, in strict mode, warnings; no data upgrade is required for diagnostics.

## Next major: isolated schema identities

This is a breaking schema identity change. Back up storage and migration history, pause writes/workers, install this code with automatic package migrations disabled, and select one owner for migrations (vendor or published).

```sh
php artisan nvl:doctor --strict --format=json
php artisan nvl:schema:upgrade --package=seo --claim-legacy --dry-run --format=json
php artisan nvl:schema:upgrade --package=seo --claim-legacy --format=json
```

The command validates released columns and relational keys plus creating migration history, renames owned legacy tables to the effective `tables.*` targets and rewrites exact package migration identities while retaining batches and unrelated host records. It refuses foreign/incomplete shapes and conflicting targets. Explicit old table mappings retain those names; remove them when choosing new defaults. A second run is empty.

Unmodified published files, including changed timestamps, map by verified checksum to the exact vendor migration identity and current package migration implementation. Modified host copies remain host-owned. Disable vendor loading when retaining a published owner; duplicate ownership fails before migration. No migration files or stored morph types are rewritten.

DDL transactions are driver dependent and per connection. Inspect dry-run warnings for MySQL/MariaDB or split storage; after a failure, inspect completed steps before resuming. Schema-qualified rename targets require an explicit host schema move first. Re-enable your selected migration owner, migrate remaining package changes and rerun Doctor before resuming writes. See the suite upgrade guide for shared owner/locale inputs, Core option defaults and one-major deprecation rules.
