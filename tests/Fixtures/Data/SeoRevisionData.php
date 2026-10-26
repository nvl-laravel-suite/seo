<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests\Fixtures\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;

/** Validated optimistic revision for profile deletion. */
final class SeoRevisionData extends SeoManagementInputData
{
    public function __construct(#[MapInputName('expected_revision')] #[MapOutputName('expected_revision')] public readonly int $expectedRevision) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return ['expected_revision' => ['required', 'integer', 'min:1']];
    }
}
