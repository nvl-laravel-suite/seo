<?php

declare(strict_types=1);

namespace Nvl\Seo\Data;

/** Validated optimistic revision for profile deletion. */
final class SeoRevisionData extends SeoManagementInputData
{
    public function __construct(public readonly int $expectedRevision) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return ['expectedRevision' => ['required', 'integer', 'min:1']];
    }
}
