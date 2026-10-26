# NVL Light migration

Requires PHP 8.4 and Laravel 13. Replace package Actions and transport DTOs with `SeoProfiles`, `SeoReader` and `SeoRedirects`. Models, domain values and documented arrays are the service boundary. Consumers own their DTOs, frontend types, editor assembly, controllers, routes and redirect middleware.

The deprecated Tenancy bridge is removed. Preserve populated profile, translation and redirect facts through native ownership adoption. Keep canonical owner admission, actor policy, revisions, URL/host validation, escaping, redirect conflicts/cycles and scoped caches inside the services.

`SeoHeadRenderer::render($owner, $actor, $locale, $scope)` produces escaped markup. Consumers deliver sitemap/robots artifacts and map native redirect results to HTTP responses. Optional Content and Media adapters remain explicitly enabled and authorized. See [native integration](README.md).
