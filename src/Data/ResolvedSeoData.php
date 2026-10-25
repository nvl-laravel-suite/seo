<?php

declare(strict_types=1);

namespace Nvl\Seo\Data;

use Nvl\Data\Traits\DataTransform;
use Spatie\LaravelData\Data;

/**
 * Complete, frontend-neutral metadata resolved for one page locale.
 *
 * @api
 */
final class ResolvedSeoData extends Data
{
    use DataTransform;

    /**
     * @param  array<string, string>  $alternates
     * @param  array<string, string>  $openGraph
     * @param  list<string>  $openGraphLocales
     * @param  array<string, string>  $twitter
     * @param  list<array<string, mixed>>  $structuredData
     */
    public function __construct(
        public readonly string $locale,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly ?string $canonicalUrl,
        public readonly string $robots,
        public readonly array $alternates,
        public readonly array $openGraph,
        public readonly array $openGraphLocales,
        public readonly array $twitter,
        public readonly array $structuredData,
    ) {}
}
