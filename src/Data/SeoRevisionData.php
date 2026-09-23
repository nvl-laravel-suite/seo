<?php

declare(strict_types=1);

namespace Nvl\Seo\Data;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Validated optimistic revision for profile deletion. */
#[TypeScript]
final class SeoRevisionData extends SeoManagementInputData
{
    public function __construct(public readonly int $expectedRevision) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return ['expectedRevision' => ['required', 'integer', 'min:1']];
    }
}
