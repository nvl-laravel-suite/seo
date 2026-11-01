---
name: nvl-seo
description: Use when integrating nvl/seo localized profiles, canonical and social metadata, JSON-LD, redirect graphs, sitemap artifacts, robots output, owner visibility or native model extensions.
---

# NVL SEO

Use PHP 8.4 / Laravel 13 native services. The application owns its DTOs,
Actions, editors, controllers, routes, redirect middleware and HTTP headers.

## References

- [Installation](references/installation.md): dependencies, provider configuration and migration/publication choices.
- [Native API](references/api.md): complete service, contract and domain-value declarations.
- [Usage](references/usage.md): policy/owner registration, profile and redirect recipes, artifacts and extensions.

## Select the service

| Capability | Native service |
| --- | --- |
| Profile create/update, archive/delete, duplicate/import | `SeoProfiles` |
| Authorized profile reads, owner batches, revisions and metadata resolution | `SeoReader` |
| Redirect mutation, management lookup, public resolution and pruning | `SeoRedirects` |
| Escaped head markup from admitted owner or resolved data | `SeoHeadRenderer` |
| Sitemap XML/index/chunks and robots text | `SitemapGenerator`, `RobotsGenerator` |
| Lookup profile by normalized path/locale/scope | `SeoRouteResolver` (lookup, not owner authorization) |
| Host owner/source/schema.org registrations | `SeoOwnerRegistry`, `SitemapRegistry`, `StructuredDataRegistry` |

## Authorization and canonical state

Bind `SeoAuthorization` or configure `nvl-seo.authorization.ability` for the
Gate-backed adapter. That default accepts persisted authenticatable actors only;
public/background execution needs an explicit host authorization binding.
Every service call supplies an actor where its contract requires one.

Host policy receives canonical persisted actor facts. Keep owner model scopes,
publication rules and active native Ownership predicates intact when admitting
profiles, resolving metadata or generating owner-derived artifacts.
Redirect mutations check both the proposed normalized identity and the stored
locked source/scope/locale. Public resolution grants no management authority.

## Inputs and native outputs

- Use native arrays, registered owner models and real domain values.
- Profile attributes and localized translation fields use their documented camelCase keys.
- Preserve omission versus nullable fields and explicit translation Patch/Replace mode.
- Send `expected_revision` for contested profile/redirect edits; new-only guards use zero.
- APIs with separate expected-revision parameters require those exact arguments.
- Reader resolution returns bounded metadata arrays; mutation returns are native models.
- Keep host/URL restrictions, canonical paths, locale support and safe URL escaping.
- A resolved metadata array is trusted data for rendering, not a policy bypass for raw input.

## Model identity and optional adapters

Configure `nvl-seo.models.profile` and `.redirect` before provider boot with concrete
native subclasses. Keep UUIDs, native required casts, query/persistence hooks and
ownership declaration; preserve effective table/connection for fixed translations
and redirect lock records. The selection is immutable for the application lifetime.
Existing morph aliases remain readable. Add attributes with host migrations.
`SeoProfileTranslation` and technical lock records are not replaceable roots.

SEO has no required Media or Content runtime. Direct URLs work with the native
image resolver. Choose actual image, structured-data and sitemap adapters for
optional sources; do not invent a global Media/Content integration switch.
Owned image/sitemap adapters must implement their ownership-safe capability.

## Transactions, artifacts and checks

Profile changes and redirect graphs own source-connection transactions and locks.
Sitemap invalidation follows the outer commit and its captured scope/owner context.
Use the artifact/cache protocol for generated sitemaps; the host delivers XML/text.
Do not substitute a cache key shared across owners or rebuild markup from unsafe strings.

Test canonical authority, owner visibility, stale revisions, path conflicts,
redirect cycles, escaped metadata, bounded sitemap chunks and rollback/invalidation.
Use [usage](references/usage.md) to choose registrations and exact recipe inputs;
report package-local evidence without claiming whole-application release readiness.
