# Upgrading NVL SEO

## 6.0.1 consolidation patch

This package requires Core 6.0.1 or later and, when installed, Ownership 6.0.1 or later. Ownership remains optional. Update the installed NVL dependency closure together so Composer can select compatible patches.

This patch introduces no schema migration or data conversion. Keep existing model subclasses, ownership declarations, adoption cursors and queued payloads. Existing source transactions, immutable owner checks and legacy exception contracts are retained. Deploy with the normal configuration-cache rebuild and worker restart; a completed adoption does not need to be repeated.

## Major 6 ownership rollout

Use verified public owner-site admission for owned profiles, redirects, sitemap and social-image work. Preserve canonical origin, scope, locale, cache and artifact identity.

Upgrade the selected NVL dependency closure together to `^6.0`. Ordinary unowned hosts retain the default unscoped declarations and need no ownership context. For isolation, use the installed Ownership README and UPGRADING guide, exact configured subclasses and reviewed adoption before accepting traffic. Disabling or removing Ownership is not rollback; Core retains durable denial guards. Keep existing tenant IDs and migration history. The explicit Tenancy bridge remains deprecated through major 6; its retirement is a separate reviewed migration no earlier than major 7.


## Consumer contracts, committed events and runtime policy (5.x)

Prefer focused public interfaces in constructor injection; native implementations remain container defaults and host prebindings win. Returned models are documented identity/data handles: use package contracts for reads/writes and capability-specific batch readers instead of direct package queries. Enable the shipped Core PHPStan include in your host; do not invoke the suite workbench static audit command in a consumer.

Events now carry immutable schemaVersion=1 and scalar/DTO snapshots. Replace model-bearing event fields with the IDs listed in [events](docs/events.md); load only through an authorized public reader when needed. Only six declared legacy `*Event` names are retained as PHP aliases for major 5, removal no earlier than major 6. Migrate exact imports/listeners/fakes to canonical names, replace suffix wildcard patterns explicitly, drain old queued payloads, rebuild event caches and restart workers. Framework Verified/PasswordReset remain native classes. Source-connection callbacks are process-local after-commit publication, not a durable outbox or exactly-once delivery.

Package failures have a marker and optional response metadata. Opt into Core's JSON renderer deliberately; preserve existing host handlers and request-locale selection. Missing required host adapters produce `binding_required`/500; genuine configured authorization denial retains native handling. See the README error table and required-bindings section where applicable.

Factories ship in runtime package mappings for host tests. Ordinary make may persist parents; withoutParents()->make creates detached fixtures. Supply persisted native owners/parents and active tenants explicitly, retain source revisions, and never treat a factory row as a real storage/provider/workflow effect. Core's optional installer publishes common config without enabling features; strict Doctor and explicit deployment cache/worker steps belong in the host release process. The PHP 8.4/Laravel 13 local Dagger release gate and fresh public Composer installation passed for 5.0.0. Additional compatibility legs need separate evidence; hosts must verify their own adoption.


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

1. Set `nvl-seo.migrations.enabled=false` for an existing adopted schema. Clean
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
8. Choose `nvl-seo.structured_data.mode`; register resource providers for generated
   schemas and retain persisted nodes only for editor-owned exceptions.
9. Replace `nvl-seo.management.prefix` with `nvl-seo.management.path`, configure
   `nvl-seo.management.name`, and configure `nvl-seo.routes.name` for public routes.
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

Declare owner classes in `nvl-core.owners`, for example `'owners' => [Article::class]`, and reference the same model class from each package capability. Laravel's `getMorphClass()` is the stored owner identity: it returns the host-authored morph alias or the FQCN when no map exists. Core declarations and package allowlists do not add or enforce a host morph map and do not grant authorization.

Legacy alias references remain read compatibility during major 5 and remain deprecated in major 6. A legacy configured alias must agree with the model's current `getMorphClass()`; mismatches are diagnostics and require a host decision. Doctor can inspect declared package owner columns for stored-versus-current identities without rewriting them. If the host introduces or changes its morph map, review and convert only the affected stored columns and reconcile host relationships before cutover. No automatic owner-data conversion or `nvl:owners:upgrade` is provided. Rebuild configuration caches and restart workers after the coordinated change.

## Shared Doctor integration

The loaded package provider now contributes its existing inspection checks to Core's `nvl:doctor --strict --format=json`. The package command remains available. The shared gate fails errors and, in strict mode, warnings; no data upgrade is required for diagnostics.

## Next major: isolated schema identities

This is a breaking schema identity change. Back up storage and migration history, pause writes/workers, install this code with automatic package migrations disabled, and select one owner for migrations (vendor or published).

```sh
php artisan nvl:doctor --strict --format=json
php artisan nvl:schema:upgrade --package=seo --claim-legacy --migration-owner=vendor --dry-run --format=json
php artisan nvl:schema:upgrade --package=seo --claim-legacy --migration-owner=vendor --format=json
```

The command validates released columns and relational keys plus creating migration history, renames owned legacy tables to the effective `tables.*` targets and rewrites exact package migration identities while retaining batches and unrelated host records. It refuses foreign/incomplete shapes and conflicting targets. Explicit old table mappings retain those names; remove them when choosing new defaults. A second run is empty.

Declare each published path and canonical identity explicitly in `nvl-core.migrations.published`; retimestamped history also needs an exact `legacy` mapping. Use `--migration-owner=vendor` after manually archiving declared copies outside loaded paths, or `--migration-owner=published` after manually replacing executable copies with current migration code and disabling vendor loading. The plan verifies ownership and preserves batches; checksums do not automatically claim files. Modified host copies remain host-owned. No migration files or stored morph types are rewritten.

DDL transactions are driver dependent and per connection. Inspect dry-run warnings for MySQL/MariaDB or split storage; after a failure, inspect completed steps before resuming. Schema-qualified rename targets require an explicit host schema move first. Re-enable your selected migration owner, run `nvl:schema:preflight` with the same selected paths and connection, then migrate remaining package changes and rerun Doctor before resuming writes. See the suite upgrade guide for shared owner/locale inputs, Core option defaults and one-major deprecation rules.

## Major 5 cache and lock identities

The default sitemap cache prefix is `nvl:seo:sitemap` instead of `nvl-seo:sitemap`. An explicitly configured `nvl-seo.sitemap.cache_key` remains host-selected. Old default entries expire naturally; NVL never copies or removes a generic host entry.

Drain old mutation workers and maintenance processes, then wait for their outstanding lock leases to end before starting the new major across all nodes. Running old and new lock prefixes concurrently would create independent serialization domains. Restart workers after cutover; preserve host-selected stores and keys, and do not flush a shared cache to remove old NVL entries.

## Tagged consumer PHP boundary

Use source `@api` workflows, extension contracts, and value types for application integration. Direct use of untagged implementations or `@internal` members is unsupported. This classification keeps existing concrete Action signatures and runtime behavior; it does not authorize package model persistence, ad hoc queries, relation traversal, or generic model serialization. Returned models are identity/result handles with only the explicitly declared in-memory read fields described in the README.

The implementation Actions `PruneSeoRedirectsAction` are explicitly internal. Run `nvl:seo:redirects:prune` for retention maintenance; use the supported redirect Actions for application changes.

`HasSeo::seoProfiles()` are internal storage or lifecycle seams. Migrate direct traversal, eager/lazy loading, and aggregate queries to the package authorized read Actions or explicit C1 host scopes/adapters. Native host queries and opted-in Translatable behavior remain supported.

`SeoProfileData::fromModel`, `SeoProfileTranslationData::fromModel`, and `HasSeo::seoProfile` are internal projectors/query helpers. Use `GetOwnerSeoProfileAction` or `ListOwnerSeoProfilesAction` for authorized owner projections instead of loading or projecting package relations.

## Application workflow contracts

Image and sitemap artifact defaults now use bindIf. Public source/authorization APIs and native transient extension lifetimes are retained; redirect pruning remains internal.

The supported workflow injection names are `ArchiveSeoProfileContract`, `DeleteSeoProfileContract`, `DuplicateSeoProfileContract`, `GetOwnerSeoProfileContract`, `GetOwnerSeoRevisionContract`, `GetSeoProfileContract`, `ImportSeoProfilesContract`, `ListOwnerSeoProfilesContract`, `ListSeoProfilesContract`, `PreviewSeoProfileContract`, `SeoProfileStatusContract`, `SyncSeoProfileContract`, `SyncSeoRedirectContract`.

Inject these contracts when application workflows need substitution. Native
concrete constructors and operation signatures remain available through major 5;
internal workflow chains are unchanged. Register host implementations before
package discovery or replace the contract before resolving a new host service.
See [Testing your app](README.md#testing-your-app) for native fixtures and the
shipped consumer-audit PHPStan configuration.
