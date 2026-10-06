<?php

declare(strict_types=1);

return [
    'routes' => ['enabled' => false, 'middleware' => ['web'], 'name' => 'nvl.seo.', 'sitemap_path' => 'nvl/seo/sitemap.xml', 'sitemap_chunk_path' => 'nvl/seo/sitemap-{chunk}.xml', 'sitemap_scopes' => [], 'robots_path' => 'nvl/seo/robots.txt'],
    'management' => ['enabled' => false, 'path' => 'nvl/api/v1/seo', 'name' => 'nvl.seo.management.', 'middleware' => ['api', 'auth', 'throttle:60,1']],
    'authorization' => ['ability' => null],
    /*
    | Stable aliases used by management/import boundaries and owner-centric
    | profile read Actions. Model relations, metadata rendering, and profile
    | mutation Actions do not require registry membership.
    */
    'owners' => [],
    'migrations' => ['enabled' => true],
    'redirects' => ['enabled' => true],
];
