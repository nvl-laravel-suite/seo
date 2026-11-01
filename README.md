# NVL SEO

Localized SEO profiles, redirects, escaped head markup, structured data, sitemaps and robots artifacts for PHP 8.4 and Laravel 13.

Consumers own data objects, Actions, controllers, middleware, routes, previews and response resources. Services retain validation, explicit actor policy, canonical ownership, revisions, locks, cache boundaries and committed invalidation.

Authorization contexts reload canonical persisted actors before host policy evaluation. Redirect updates authorize both the stored identity under lock and the proposed scope, source and locale; permission for a new destination cannot grant authority over an existing redirect in another scope.

## Model replacement

Configure `nvl-seo.models.profile` and `nvl-seo.models.redirect` before provider boot with concrete subclasses of `Nvl\Seo\Models\SeoProfile` and `Nvl\Seo\Models\SeoRedirect`. Native services, owner/profile and translation/profile relations, route/sitemap queries, factories and Ownership adoption use the selected roots. Existing morph aliases remain readable; both selections are immutable for the application lifetime. Subclasses may add attributes, casts, accessors and relations while retaining UUID identity, required casts, query/persistence hooks and the native ownership declaration. Native invariant listeners are final; use an observer or bootable trait for additional listeners. Tables and connections remain package-configured, and a selected root must match its native effective storage so fixed translations and technical lock records share its transaction source. Added columns require host migrations. `SeoProfileTranslation` and technical records are not replaceable.

## Native services

- `SeoProfiles`: sync, status, duplicate, archive, delete and import complete profile operations.
- `SeoReader`: authorized resolution, owner reads, finite owner batches, revisions and pagination.
- `SeoRedirects`: sync, find, resolve and prune with native conflict/cycle handling.
- `SeoHeadRenderer`: produce escaped head markup from an admitted owner and explicit actor.
- `SitemapGenerator` and `RobotsGenerator`: produce domain artifacts; consumers implement HTTP delivery and cache headers.

```php
use Nvl\Seo\Services\SeoHeadRenderer;

$head = app(SeoHeadRenderer::class)->render($owner, $actor, locale: 'en');
```

Services use native models, enums and documented arrays. Consumers project model fields and assemble editor interfaces. Bind host authorization and reference/image adapters. A background or public identity does not implicitly grant access.

Resolution reloads canonical registered owners and native profiles from their configured storage before authorization, image or structured-data providers run, including without Ownership. Dirty attributes and loaded relations cannot replace persisted facts; changed identity, table or connection is rejected. A stale in-memory active profile cannot resurrect persisted inactive metadata.

SEO works unowned without Auth or the deprecated Tenancy bridge. Native ownership is explicit: register the host resources, configure owner-resource keys, adopt populated storage and supply admitted contexts. Preserve profile/locale parent identity and owner-local path/redirect constraints.

Enable optional Content or Media integration explicitly and retain its authorization boundary. Private preview and private image references need the corresponding actor policy; public output must honor publication, locale, ownership and URL policy.

The package registers no HTTP routes or redirect middleware. Consumer endpoints serve generated artifacts, apply cache headers and turn resolved redirect facts into responses. Do not bypass service visibility checks by querying private profiles for a public response.

`nvl-seo.redirects.record_hits` defaults to true: each successful `SeoRedirects::resolve` synchronously increments the matched redirect's hit counter. Set it false before serving a high-volume or read-only redirect path; external analytics then belong to the host. `find` is a management read and never records a hit. Profile the actual storage workload before enabling counting on a hot redirect.

Choose one migration owner and review configuration before migrating populated tables. See the [source services](src/Services/SeoProfiles.php), [events](docs/events.md), [upgrading](UPGRADING.md), [security](SECURITY.md) and [contributing](CONTRIBUTING.md).

Shared [service contracts, model extensions and composition](https://github.com/nvl-laravel-suite/laravel-suite/blob/main/docs/consumer-api.md) apply alongside the package-specific rules above.
