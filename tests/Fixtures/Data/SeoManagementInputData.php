<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests\Fixtures\Data;

use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Data;

/** Strict management input boundary shared by SEO command Data objects. */
abstract class SeoManagementInputData extends Data
{
    use DataTransform;

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [];
    }

    public static function withValidator(Validator $validator): void
    {
        self::rejectUnknownFields($validator, static::rules());
    }

    /** @param array<string, mixed> $rules */
    public static function rejectUnknownFields(Validator $validator, array $rules): void
    {
        $validator->after(static function (Validator $validator) use ($rules): void {
            $allowed = array_unique(array_map(static fn (string $field): string => Str::before($field, '.'), array_keys($rules)));
            foreach (['per_page' => 'perPage', 'expected_revision' => 'expectedRevision', 'translation_mode' => 'translationMode'] as $field => $property) {
                if (in_array($field, $allowed, true)) {
                    $allowed[] = $property;
                }
            }

            foreach (array_keys($validator->getData()) as $field) {
                if (is_string($field) && ! in_array($field, $allowed, true)) {
                    $validator->errors()->add($field, "The {$field} field is not supported.");
                }
            }
        });
    }
}
