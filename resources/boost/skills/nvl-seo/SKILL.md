---
name: nvl-seo
description: Implement, integrate, test, or review nvl/seo in Laravel 13. Use for polymorphic SEO profiles, localized metadata through nvl/translatable, canonical and hreflang URLs, Open Graph and Twitter cards, media-backed social images, safe JSON-LD, robots directives, sitemap sources/routes, path uniqueness, Blade head rendering, or SEO package architecture.
---

# NVL SEO

For major 6, use native Ownership for new isolated integrations and the installed
Ownership README/UPGRADING runbook for configured subclasses and reviewed adoption.
Ordinary unowned hosts keep unscoped declarations; installation does not enable
isolation. Tenant-specific APIs below apply only to the explicitly selected
deprecated Tenancy bridge; they never substitute for native owner admission.

## Native ownership and verified public admission

- Keep the default unscoped graph independent from NVL Auth, Tenancy and Ownership.
- Use the package README ownership section for configured roots, exact native host
  resources and reviewed per-row adoption; never infer an owner from request input.
- Resolve owned public work with `OwnerSiteResolver` and `PublicOwnerSiteRunner`.
  Use only the admitted `VerifiedOwnerSiteContext` for owner/site/origin/resources;
  preserve locale and content scope as separate identities. Public admission cannot
  become actor/system/central/queue authority.
- Retain actual-source transactions, native parent/pivot constraints and canonical
  re-reads. Existing tenant rows/IDs/morph values and released migrations remain intact.
- Declare `nvl-seo.ownership.owner_resources`; keep profiles inherited and redirects
  captured. Use ownership-safe image/sitemap contracts and owner-local artifacts.
- Warm scheduled owned sitemaps with `--origin` through the verified public resolver.

## Deprecated Tenancy bridge: Tenant/site identity

- In adopted mode, require the active tenant and verified `TenantSiteContext`.
  Scope must equal the verified site; canonical, alternate, sitemap, and robots
  URLs use its canonical origin rather than mutable configuration.
- Register canonical owners/resources and reload supplied models before profile,
  image, structured-data, redirect, or sitemap work. Unknown types fail closed.
- Register sitemap sources by class with `registerType()`. They implement
  `TenantSafeSitemapSource`, declare all queried resources, and are resolved
  freshly inside the active tenant scope.
- Media-backed social-image adapters implement `TenantSafeSeoImageResolver` and
  validate the canonical tenant reference before resolving the image.
- Capture connection/tenant/site/origin/scope/version identity with the
  mutation and invalidate only that namespace after commit. Adopt and rehash
  existing redirects under maintenance before final constraints.

Treat SEO as a resolved discoverability contract, not a collection of arbitrary meta-tag columns. Keep storage, locale fallback, URL identity, rendering, and crawl discovery consistent.

## Attach a profile

Use `SyncSeoProfileAction` with a persisted Eloquent owner and `SeoProfilePayload`. Pass translations as a locale-keyed map. Use patch mode by default; require explicit replace mode before deleting omitted locale rows.

Add `HasSeo` to supply the package capability. Read profiles through `GetOwnerSeoProfileAction` for one owner or `ListOwnerSeoProfilesAction` for an owner iterable, with an optional scope. Both invoke the configured `SeoAuthorization` internally and accept no actor argument; `seoProfiles()` is an internal relation. Never write `seo_profiles` or `seo_profiles_i18n` directly.

Polymorphic owners cannot use database-level cascading foreign keys. Keep owner
deletion policy in the host application's Action: remove profiles through
`DeleteSeoProfileAction` before hard deletion, or deliberately retain/archive
them for soft-deleted owners. Do not add a hidden model observer.

## Localize content

- Store titles, descriptions, paths, social overrides, image copy, and structured data in dedicated translation rows.
- Resolve them through `nvl/translatable`; do not use insertion order or Laravel's UI locale as an implicit content fallback.
- Keep profile scope, crawl directives, and sitemap policy on the owner row.
- Preserve intentional empty translated values; fall back only under `nvl/translatable` semantics.
- Treat the central `seo.profiles` resource as read-only discovery/reporting.
  Its domain-action-only policy protects revision, conflict, event, and sitemap
  invariants; write through `SyncSeoProfileAction`.

## Keep URLs consistent

- Normalize paths through `SeoPath`.
- Treat percent-encoded unreserved characters as their canonical literal form;
  reject malformed, nested, or encoded-separator path identities.
- Rely on the database uniqueness key for site scope + locale + normalized path.
- Render absolute canonical URLs.
- Include self-referential canonicals and reciprocal localized alternates.
- Use `SeoRouteResolver` when resolving content from an incoming localized path.

## Render metadata

Resolve with `SeoMetadataResolver`; render with `SeoHeadRenderer`, `SeoManager`, or `@nvlSeo($owner)`.

- Escape every attribute and title.
- Encode JSON-LD with all HTML-sensitive JSON flags.
- Do not accept pre-rendered tags or raw script strings.
- Build one-off schema.org objects with `StructuredDataBuilder`.
- Use `StructuredDataProvider` and `StructuredDataRegistry` for repeatable,
  resource-aware nodes; providers receive `StructuredDataContextData`.
- Return `StructuredDataNodeData` with stable `@id` values so nodes connect in
  one deterministic graph.
- Accept schema.org extension types as safe strings; do not treat
  `StructuredDataType` as a closed allowlist.
- Keep generated facts accurate to visible resource content. Treat structural
  validation and search-engine rich-result eligibility as separate concerns.
- Keep Open Graph/Twitter overrides optional so primary metadata remains the fallback.

## Integrate media

Direct URLs work through `DirectSeoImageResolver`. For `nvl/media`, bind `SeoImageResolver` in the host application and resolve `SeoImageContext::reference` through `MediaAssetService`; the context already contains field-level fallback values. Do not add a hard media dependency to this package or assemble media storage paths in SEO code.

## Publish discovery files

Enable package routes only when the application does not already own `/sitemap.xml` or `/robots.txt`.

- Register additional domain sources through `SitemapSource`.
- Register exclusive profile ownership with `SitemapRegistry::register()`'s
  `ownerTypes` model-class argument when an owner package controls publication eligibility.
  Apply owner and SEO exclusions before reusing `entriesForProfile()` with
  eager-loaded translations; do not reintroduce excluded profiles as fallback URLs.
- Yield entries lazily; do not load an entire catalog into memory.
- Keep chunks within configured URL and uncompressed-byte limits and expose a
  sitemap index when multiple chunks exist.
- Store completed XML artifacts on a durable private filesystem disk. Keep only
  small manifests and versions in the cache.
- Use an atomic-lock-capable cache store, unique source keys, completed
  manifests, ETags, and after-commit version invalidation.
- List every public non-default scope in `nvl-seo.routes.sitemap_scopes`; never
  accept arbitrary public scope keys that can create unbounded cache namespaces.
- Preserve scope on sitemap-index chunk links and rebuild once when a completed
  manifest points to a missing filesystem artifact.
- Enforce fragment-free, same-origin URL identity and sitemap path scope.
- Omit valid external canonicals from the built-in site sitemap; keep strict
  rejection for invalid custom-source locations.
- Emit `lastmod` only when the source owns an accurate content-modification
  timestamp.
- Use robots meta `noindex` to prevent indexing; do not treat `robots.txt` disallow as an indexing control.
- Invalidate sitemap cache only after committed profile mutations.

## Manage and adopt

- Keep the API disabled by default and configure both
  `nvl-seo.management.path` and `nvl-seo.management.name`.
- Register stable aliases in `nvl-seo.owners`; never accept model class names at
  HTTP/import boundaries.
- Normalize both integer and string owner identifiers into the package's stable
  string storage boundary.
- Authorize with the complete `SeoAuthorizationContext`, including source and
  target owners during duplicate operations.
- Use revision `0` for race-safe creates and exact positive revisions for
  updates, archive/restore, and delete.
- Use `ownerAlias` in `SeoImportRecordData`.
- Preserve safe redirect query strings/fragments and reject network-path or
  non-HTTP external targets.
- Detect redirect loops through decorated and same-site absolute targets, and
  preserve omitted metadata during redirect updates.
- Prefer exact-locale redirects, then locale-neutral fallbacks. Prune retained
  soft-deleted redirects with `nvl:seo:redirects:prune`.
- Run the redirect-lock migration. Redirect Actions serialize graph mutations
  through commit and validate every configured locale's effective fallback graph.

## Read profiles from owners

- Use `Nvl\Seo\Actions\GetOwnerSeoProfileAction::execute(
  Illuminate\Database\Eloquent\Model $owner, ?string $scope = null)` for an
  authorized `Nvl\Seo\Data\SeoProfileData` with translations, or `null` when
  absent.
- Use `Nvl\Seo\Actions\GetOwnerSeoRevisionAction::execute(Model $owner,
  ?string $scope = null)` when a mutation needs only
  `Nvl\Seo\Data\SeoOwnerRevisionData`. Its camel-case fields are
  `string $ownerAlias`, `string $ownerId`, `string $scope`,
  `?string $profileId`, and `int $revision`. Revision `0` and a null profile ID
  mean that the scoped profile does not exist.
- Use `Nvl\Seo\Actions\ListOwnerSeoProfilesAction::execute(iterable $owners,
  ?string $scope = null)` for bounded collection composition. It accepts at
  most 100 persisted owners, authorizes all of them before profile SQL, and
  returns positional profile-or-null results with translations in fixed queries.
- Register the owner's stable alias in `nvl-seo.owners` and bind
  `SeoAuthorization` or configure its Gate ability. These Actions normalize the
  scope, resolve the alias/morph identity, and authorize `SeoAbility::View`
  before querying profiles.
- Do not navigate `seoProfiles`, manually choose a scoped raw model, or call
  `SeoProfileData::fromModel()` with an application-hardcoded alias.

Use these exact imports and calls:

```php
use Nvl\Seo\Contracts\GetOwnerSeoProfileContract;
use Nvl\Seo\Contracts\GetOwnerSeoRevisionContract;
use Nvl\Seo\Contracts\ListOwnerSeoProfilesContract;

$profile = app(GetOwnerSeoProfileContract::class)->execute($page, $page->site);
$revision = app(GetOwnerSeoRevisionContract::class)->execute($page, $page->site);
$profiles = app(ListOwnerSeoProfilesContract::class)->execute($pages, $page->site);
```

## Verify

Test management Actions, exact locale and fallback reads, patch/replace writes,
expected revisions, path normalization and races, scope isolation, canonical
and hreflang parity, social-image fallbacks, redirects and loops, XSS-safe
output, provider priority and resource matching, graph identity/merge rules,
JSON-LD limits, sitemap indexes, cache invalidation, authorization, query
counts, and cascade deletion. Run `nvl:seo:doctor --strict --format=json`, the
package Pest suite, Pint, PHPStan at maximum strictness, dependency audit, and
Laravel 13 gates.

## Ownership isolation and release discipline

- Keep unowned applications independent of the enforcement runtime; native ownership never adds an undeclared Auth or Suite dependency. Tenancy-specific APIs are deprecated bridge compatibility.
- Use registered package-owned resources, adoption adapters, Actions, and lifecycle APIs. Never add a generic owner delete-all path or raw cross-package cleanup.
- Treat mapping/configuration hashes, interruption checkpoints, conservation evidence, worker context, owner-partitioned queries, and standalone consumption as release contracts.

## Shared owner identities

- Declare owner class lists in `nvl-core.owners` and reference model classes in `seo` capability configuration. Laravel `getMorphClass()` supplies the host-authored stored identity; declarations do not add global host morph mappings.
- An alias-to-alias map may retain a distinct API key, for example owners = ['articles.detail' => 'article']. Preserve SEO mutation authorization and scopes.
- Keep the package allowlist and authorization independent of Core registration. Never authorize a model merely because Core knows it.
- Preserve resolvers, handlers and authorization. Legacy aliases require agreement with native `getMorphClass()` and remain deprecated in major 6; Doctor reports mismatches and stored identity drift without conversion.
- If the host changes its morph map, explicitly reconcile reviewed package-owned columns and affected host relations before cutover. Core and package capability registration never mutate the host morph map or rewrite stored values.

## Shared consumer diagnostics

Run `php artisan nvl:doctor --strict --format=json` to combine checks from loaded NVL providers. Retain the package Doctor command for its detailed report; both paths reuse the package-owned inspection service.

### Brownfield storage identities

Resolve all package tables through the table helper and canonical `nvl-seo.tables.*`, connections through `nvl-seo.connection` with Core/Laravel inheritance. Defaults use `nvl_seo_*`; migration filenames include that package slug. Never silently adopt a matching table or generic migration filename. Run shared `nvl:doctor --strict --format=json` and the explicit `nvl:schema:upgrade --package=seo --claim-legacy --dry-run --format=json` before upgrading owned legacy storage. Validate the complete plan and choose one migration owner. Preserve host records, constraint names and stored morph values. Deprecated config inputs last one major; canonical options take precedence.

## Cache and lock ownership

The default sitemap cache prefix is `nvl:seo:sitemap` instead of `nvl-seo:sitemap`. An explicitly configured `nvl-seo.sitemap.cache_key` remains host-selected. Old default entries expire naturally; NVL never copies or removes a generic host entry.

Drain old mutation workers and maintenance processes, then wait for their outstanding lock leases to end before starting the new major across all nodes. Running old and new lock prefixes concurrently would create independent serialization domains. Restart workers after cutover; preserve host-selected stores and keys, and do not flush a shared cache to remove old NVL entries.

## Canonical configuration ownership

- Read/write `nvl-seo` configuration and publish only canonical `nvl-<package>-<resource>` tags. Keep logical package/tenant resource identifiers unchanged.
- Generic config roots and unprefixed package environment names are foreign by default. For an upgrading NVL host only, select `nvl-core.compatibility.legacy_config` package IDs and `legacy_env` explicitly; both default off. Canonical presence wins, including false/null/empty values. Legacy inputs are read without writing back and remain deprecated in major 6.
- Use canonical `NVL_<PACKAGE>_*` variables only in config evaluation, then rebuild configuration caches and restart workers after cutover. Shared Laravel environment variables retain their names. Consult Core's versioned `support/resources/global-names.json` for all renames.
- Old global aliases and legacy route families require separate explicit `global_aliases`/`legacy_routes` package selections. Preserve collisions and use Doctor diagnostics; never grant generic permissions automatically or claim signed-link compatibility without the same authorization/signature checks.

## Application workflow substitution

Image and sitemap artifact defaults now use bindIf. Public source/authorization APIs and native transient extension lifetimes are retained; redirect pruning remains internal.

The supported workflow injection names are `ArchiveSeoProfileContract`, `DeleteSeoProfileContract`, `DuplicateSeoProfileContract`, `GetOwnerSeoProfileContract`, `GetOwnerSeoRevisionContract`, `GetSeoProfileContract`, `ImportSeoProfilesContract`, `ListOwnerSeoProfilesContract`, `ListSeoProfilesContract`, `PreviewSeoProfileContract`, `SeoProfileStatusContract`, `SyncSeoProfileContract`, `SyncSeoRedirectContract`.

Inject the supported contract into host orchestration and bind a native interface
mock or host implementation before resolving that orchestration. Keep concrete
constructors and native workflow bodies intact; internal chains remain package-owned.
Use declared DTOs or unsaved model identity handles for orchestration fixtures.
Use real package workflows and Laravel framework fakes for persistence, tenant,
queue, file, and external-effect integration checks. A host substitute proves
only the host call and result. Keep public declarations tagged `@api` and
constructor/configuration/private helpers internal.

Consult the owning README's Testing your app section for native examples. Include
`vendor/nvl/core/support/consumer-audit.neon` in host PHPStan and declare explicit
`nvlConsumer.testPaths`; the Suite workbench is not consumer tooling.


## Consumer runtime and testing contracts

Start with the package README Quickstart and Testing your app sections. Use `nvl:install <package>` for loaded-package common config publication; it does not enable features, run schema or refresh caches. Preserve native host owner keys/morph maps and selected host Auth and ownership defaults. Read full runtime defaults and publish advanced config only deliberately.

Inject the supported focused interfaces and preserve host bindings. Returned model handles do not permit package-table queries/writes outside documented capability/extension seams. Host tests may substitute contracts in Laravel's container, use shipped model factories (ordinary make may persist parents; withoutParents()->make is detached), and use Laravel effect fakes deliberately. Only Media/Stripe have dedicated provider/library fakes; do not invent a universal package fake. Settings InteractsWithSettings is definition-only. Host PHPStan may include vendor/nvl/core/support/consumer-audit.neon; no unpublished workbench command is a consumer requirement.

Read docs/events.md and the package README error table. Domain events use schemaVersion=1, model-free facts and actual source-connection commit callbacks; only six declared old Event suffix aliases remain deprecated through major 6. Migrate exact listeners/fakes and suffix wildcards, drain old queued payloads, rebuild event cache and restart workers. Delivery is not a durable outbox. The Core exception renderer is opt-in, JSON-only for respondable failures, with exactly message/code/context and host-selected locale. Do not expose diagnostics or reinterpret missing bindings as authorization denial.

Core package logging uses nvl/normal with CSV quiet by default, stable message keys and bounded context; incidents survive quiet. Do not mutate global logger context or log raw row/provider/content/credential payloads. Run only authorized project checks and report new acceptance as pending until actual output exists.
