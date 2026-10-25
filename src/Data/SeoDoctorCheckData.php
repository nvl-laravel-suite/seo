<?php

declare(strict_types=1);

namespace Nvl\Seo\Data;

use Spatie\LaravelData\Data;

/**
 * One immutable read-only SEO installation diagnostic.
 */
final class SeoDoctorCheckData extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly string $severity,
        public readonly bool $passed,
        public readonly string $message,
    ) {}
}
