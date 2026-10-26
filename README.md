# NVL SEO

Localized SEO profiles, redirects, escaped head markup, structured data, sitemaps and robots artifacts for PHP 8.4 and Laravel 13.

Consumers own data objects, Actions, controllers, middleware, routes, previews and response resources. Services retain validation, explicit actor policy, canonical ownership, revisions, locks, cache boundaries and committed invalidation.

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

SEO works unowned without Auth or the deprecated Tenancy bridge. Native ownership is explicit: register the host resources, configure owner-resource keys, adopt populated storage and supply admitted contexts. Preserve profile/locale parent identity and owner-local path/redirect constraints.

Enable optional Content or Media integration explicitly and retain its authorization boundary. Private preview and private image references need the corresponding actor policy; public output must honor publication, locale, ownership and URL policy.

The package registers no HTTP routes or redirect middleware. Consumer endpoints serve generated artifacts, apply cache headers and turn resolved redirect facts into responses. Do not bypass service visibility checks by querying private profiles for a public response.

Choose one migration owner and review configuration before migrating populated tables. See the [source services](src/Services/SeoProfiles.php), [events](docs/events.md), [upgrading](UPGRADING.md), [security](SECURITY.md) and [contributing](CONTRIBUTING.md).
