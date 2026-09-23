<?php

declare(strict_types=1);

namespace Nvl\Seo\Data;

use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Nvl\Data\Traits\DataTransform;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\Hidden;

/** Strict management input boundary shared by SEO command Data objects. */
#[Hidden]
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

            foreach (array_keys($validator->getData()) as $field) {
                if (is_string($field) && ! in_array($field, $allowed, true)) {
                    $validator->errors()->add($field, "The {$field} field is not supported.");
                }
            }
        });
    }
}
