<?php

declare(strict_types=1);

namespace Nvl\Seo\Data\Import;

use Spatie\LaravelData\Data;

/**
 * Reports one completed, bounded SEO import page.
 *
 * @api
 */
final class SeoImportResultData extends Data
{
    /**
     * Create one import-page result.
     */
    public function __construct(
        public readonly int $processed,
        public readonly ?string $nextCursor,
    ) {}
}
