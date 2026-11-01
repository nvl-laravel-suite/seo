# SEO usage and host choices

Use [installation](installation.md) for setup and [API](api.md) for exact declarations.
`App\...` classes below are host-owned implementations, not package classes.

## Policy, owners and adapters

Bind `Nvl\Seo\Contracts\SeoAuthorization` to a host implementation whose
`authorize(SeoAuthorizationContext $context): void` throws on denial. Context
includes actor, `SeoAbility`, canonical profile/owner, optional target owner,
scope and redirect source/locale. A system enum or public identity is never a grant.
Alternatively configure `nvl-seo.authorization.ability` and define that Gate;
the default `ConfiguredSeoAuthorization` requires a persisted authenticatable.
It deliberately denies `SystemActor` and `PublicActor`, even with a configured Gate.

```php
$this->app->bind(
    \Nvl\Seo\Contracts\SeoAuthorization::class,
    \App\Seo\HostSeoAuthorization::class,
);
config(['nvl-seo.owners.article' => \App\Models\Article::class]);
```

Declare stable owner aliases before boot, preserving the host's morph identity.
Services canonically reload owners and Eloquent actors, retaining host scopes;
client-provided class names, loaded relations or locally altered role attributes
cannot establish authority. Owner batches admit at most 100 inputs and retain order.

Optional adapter points are `SeoImageResolver` (`nvl-seo.image_resolver`),
`SitemapSource` (`nvl-seo.sitemap.sources` or `SitemapRegistry`),
`SitemapArtifactStore` (`nvl-seo.sitemap.artifact_store`) and
`StructuredDataProvider` (`nvl-seo.structured_data.providers`). Register provider
entries with `resource`, `provider`, optional `key` and `priority`. Use named classes
for cacheable configuration. Plain URLs and SEO output need no Content or Media.
Owned sources/images must supply `OwnershipSafeSitemapSource` /
`OwnershipSafeSeoImageResolver`, with their admitted native resource capabilities.

## Profiles and localized metadata

```php
use Nvl\Seo\Services\SeoProfiles;
use Nvl\Seo\Services\SeoReader;

$profiles = app(SeoProfiles::class);
$profile = $profiles->sync($manager, $article, [
    'expected_revision' => 0,
    'isIndexable' => true,
    'sitemapIncluded' => true,
    'translations' => ['en' => [
        'path' => '/articles/welcome',
        'title' => 'Welcome',
        'description' => 'News from the team.',
    ]],
], 'main');
$profile = $profiles->sync($manager, $article, [
    'expected_revision' => $profile->revision,
    'translations' => ['en' => ['description' => null]],
], 'main');
$metadata = app(SeoReader::class)->resolve($viewer, $article, 'en', 'main');
```

Profile keys: `isIndexable`, `isFollowable`, `maxSnippet`, `maxImagePreview`,
`maxVideoPreview`, `sitemapIncluded`, `sitemapPriority`, `sitemapChangeFrequency`,
`metadata`, `translations`, `expected_revision`. Ownership, status and actor are
not editable payload fields. Use dedicated archive/delete operations for lifecycle.

Translation keys include `path`, `title`, `description`, `canonicalUrl`, `imageUrl`,
`imageReference`, `imageAlt`, `openGraphTitle`, `openGraphDescription`, `twitterTitle`,
`twitterDescription`, `twitterCard`, `structuredData`, `metadata`. Read the API schema for exact
nullable fields and limits. Unknown fields fail. Patch keeps omitted fields/locales;
`TranslationSyncMode::Replace` explicitly removes omitted locales. An absent
translation map with replace requests deletion of the retained locale set.

`sync` accepts an optional optimistic precondition; use zero to assert a new profile
and the current revision to edit. Do not describe all sync calls as revision-required.
Separate archive/delete operations have their own explicit preconditions. Duplicate
checks both source and target owners; imports use the registered `SeoImportSource`.

`SeoReader::get/forOwner/forOwners/revisions/paginate` cover management reads.
`SeoRouteResolver::resolve($path,$locale,$scope)` looks up a profile by route; it
has no actor argument and does not replace owner authorization before presentation.
Resolution returns `locale`, `title`, `description`, `canonicalUrl`, `robots`,
`alternates`, `openGraph`, `openGraphLocales`, `twitter`, `structuredData`.
Missing/inactive profiles use configured defaults rather than exposing archived copy.

```php
$html = app(\Nvl\Seo\Services\SeoHeadRenderer::class)
    ->render($article, $viewer, 'en', 'main');
```

The renderer escapes admitted metadata, including JSON-LD encoding. `renderResolved`
accepts a trusted resolved array; it does not authorize arbitrary editor input.
Generated schema.org providers return `StructuredDataNode` values from the canonical
resource and bounded context. `structured_data.mode` selects persisted/generated/merge.
A media reference requires a host image adapter that preserves Media authorization;
registering a reference string alone does not make an asset public.

## Redirect graphs

```php
use Nvl\Seo\Services\SeoRedirects;

$redirects = app(SeoRedirects::class);
$redirect = $redirects->sync($manager, null, [
    'sourcePath' => '/old-article',
    'target' => '/articles/welcome',
    'locale' => 'en',
    'expected_revision' => 0,
], 'main');
$snapshot = $redirects->find($manager, '/old-article', 'main', 'en');
$match = $redirects->resolve($publicActor, '/old-article', 'en', 'main');
```

The differing `find` / `resolve` argument order is intentional; prefer named arguments
when it improves clarity. Sync keys are `sourcePath`, `target`, `locale`, `statusCode`,
`isActive`, `expiresAt`, `metadata`, `expected_revision`. Defaults make sync a complete
redirect mutation for omitted defaulted fields, not a generic patch. A null locale
is neutral. Metadata omission preserves existing metadata; explicit null clears it.
The optional revision precondition uses zero for creation/resurrection.

An update by model or ID authorizes both current stored identity and proposed
normalized source/scope/locale. The service flattens active chains, detects loops
including neutral/localized fallback, and serializes writes through graph locks.
`find` returns the exact management model including revision without recording hits.
`resolve` returns `id`, `source`, `target`, `statusCode` or null and may record hits.
Hit recording is synchronous and enabled by default through `nvl-seo.redirects.record_hits`. Disable it for high-volume/read-only resolution when the host owns external analytics; every successful recorded hit writes to the redirect row.
The consumer owns redirect middleware, response status/headers and query handling.
Pruning retains tombstones until the requested retention and cannot run under
public owner/site admission.

## Sitemap, robots, isolation and model extensions

`SitemapGenerator::generate($scope)` returns XML; `generateChunk($chunk, $scope)`
returns a bounded URL set and `chunkCount($scope)` reports available chunks.
`RobotsGenerator::generate()` returns text. Paths in `nvl-seo.routes` describe
artifact locations; they do not install host controllers/routes. Provide delivery
endpoints and cache/content-type policy in the application.

Sitemap sources stream `SitemapEntry` values with eligible absolute URLs. Retain
publication, owner, locale, same-origin/path-scope and active SEO exclusions.
Use the configured artifact store and commit-aware scoped cache invalidation;
external canonical profiles may be excluded from the built-in site sitemap.

Configure `nvl-seo.models.profile` and `.redirect` before providers boot. Concrete
subclasses retain native UUID, casts, effective storage, ownership declarations,
relations and invariant listeners; use observers/bootable traits for extra listeners.
The selected classes are immutable; fixed translation and technical lock models
remain native. Added columns are host migrations, never automatic package changes.

Native Ownership is optional for unowned installations. Owned models require
reviewed adoption and verified public origin/site admission before public operations.
Do not translate retired tenant columns into current admission or bypass host scopes.

Handle `InvalidSeoMutationException`, stale profile/redirect and path/loop conflicts
at the consumer boundary without exposing SQL, image credentials or owner facts.
Test direct-service role revocation, dirty/foreign owners, revisions, translation
patch/replace, redirect graph safety, output escaping, artifact limits and rollback.
