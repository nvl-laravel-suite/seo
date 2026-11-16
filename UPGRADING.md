# NVL Light migration

Requires PHP 8.4 and Laravel 13. Replace package Actions and transport DTOs with `SeoProfiles`, `SeoReader` and `SeoRedirects`. Models, domain values and documented arrays are the service boundary. Consumers own their DTOs, frontend types, editor assembly, controllers, routes and redirect middleware.

The deprecated Tenancy bridge is removed. Preserve populated profile, translation and redirect facts through native ownership adoption. Keep canonical owner admission, actor policy, revisions, URL/host validation, escaping, redirect conflicts/cycles and scoped caches inside the services.

`SeoHeadRenderer::render($owner, $actor, $locale, $scope)` produces escaped markup. Consumers deliver sitemap/robots artifacts and map native redirect results to HTTP responses. Optional Content and Media adapters remain explicitly enabled and authorized. See [native integration](README.md).

Adoption runs through nvl:adopt with reviewed, checkpointed batches and an explicit activate step. Committed batches survive a failure. Keep writers stopped until verify and activate pass. Enable `nvl-core.adoption.migrations.enabled` and migrate, then run `php artisan nvl:adopt seo plan|apply|verify|activate <manifest.json>` with the same `connection`, `scope` and `owner_aliases` options that `SeoSchemaAdopter` takes; each batch commits legacy profiles with their translations. `SeoSchemaAdopter` keeps working unchanged.
