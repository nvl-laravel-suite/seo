<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests\Fixtures\Data;

/** Validated optimistic archive or restore mutation. */
final class SeoArchiveProfileData extends SeoManagementInputData
{
    public function __construct(
        public readonly bool $archived,
        public readonly int $expectedRevision,
    ) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'archived' => ['required', 'boolean'],
            'expectedRevision' => ['required', 'integer', 'min:1'],
        ];
    }
}
