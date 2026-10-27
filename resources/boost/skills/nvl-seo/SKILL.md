---
name: nvl-seo
description: Integrate native SEO profile, redirect and artifact services.
---

# NVL SEO

Use PHP 8.4 and Laravel 13. Consumers own data objects, Actions, frontend types, editor payloads, controllers, routes, redirect middleware and HTTP headers.

Use SeoProfiles for complete profile mutations/imports, SeoReader for authorized native reads/resolution, and SeoRedirects for redirect mutations/resolution/pruning. SeoHeadRenderer renders admitted owner markup with an explicit actor. SitemapGenerator and RobotsGenerator produce domain artifacts for consumer delivery.

Retain service validation, escaping, host/URL restrictions, publication/locale/owner visibility, revisions, locking, cache scope and committed invalidation. Native models and documented arrays replace transport DTOs. Bind actual host policy and image/reference adapters. Background/public identities require explicit grants.

The deprecated Tenancy bridge is removed. SEO may remain unowned; scoped hosts register exact native resource keys and adopt populated records. Enable optional Content or Media integrations explicitly and preserve their authorization. Read the shipped README and source PHPDoc for exact native signatures.

Configure `nvl-seo.models.profile` and `nvl-seo.models.redirect` before provider boot with concrete subclasses of `Nvl\Seo\Models\SeoProfile` and `Nvl\Seo\Models\SeoRedirect`. Native services, owner/profile and translation/profile relations, route/sitemap queries, factories and Ownership adoption use the selected roots. Existing morph aliases remain readable; both selections are immutable for the application lifetime. Subclasses may add attributes, casts, accessors and relations while retaining UUID identity, required casts, query/persistence hooks and the native ownership declaration. Native invariant listeners are final; use an observer or bootable trait for additional listeners. Tables and connections remain package-configured, and a selected root must match its native effective storage so fixed translations and technical lock records share its transaction source. Added columns require host migrations. `SeoProfileTranslation` and technical records are not replaceable.
