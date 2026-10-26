<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests\Fixtures\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;

/** Validated optimistic archive or restore mutation. */
final class SeoArchiveProfileData extends SeoManagementInputData
{
    public function __construct(
        public readonly bool $archived,
        #[MapInputName('expected_revision')] #[MapOutputName('expected_revision')] public readonly int $expectedRevision,
    ) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'archived' => ['required', 'boolean'],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ];
    }
}
