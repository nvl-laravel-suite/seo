<?php

declare(strict_types=1);

return [
    'routes' => ['sitemap_path' => 'nvl/seo/sitemap.xml', 'sitemap_chunk_path' => 'nvl/seo/sitemap-{chunk}.xml', 'sitemap_scopes' => [], 'robots_path' => 'nvl/seo/robots.txt'],
    'authorization' => ['ability' => null],
    /*
    | Stable aliases used by management/import boundaries and owner-centric
    | native profile reads and mutations.
    */
    'owners' => [],
    'migrations' => ['enabled' => true],
    'redirects' => ['enabled' => true],
];
