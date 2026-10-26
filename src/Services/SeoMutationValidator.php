<?php

declare(strict_types=1);

namespace Nvl\Seo\Services;

use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Nvl\Seo\Enums\SitemapChangeFrequency;
use Nvl\Seo\Enums\TwitterCard;
use Nvl\Seo\Exceptions\InvalidSeoMutationException;
use Nvl\Seo\Rules\CanonicalUrl;
use Nvl\Seo\Rules\ValidStructuredData;
use Nvl\Seo\Support\HttpUrl;
use Nvl\Seo\Support\SeoPath;
use Nvl\Seo\Support\SeoRedirectTarget;
use Nvl\Support\Contracts\LocaleCatalog;
use Nvl\Translatable\Exceptions\InvalidLocaleException;
use Nvl\Translatable\Rules\SupportedLocaleMapRule;

/**
 * Enforces transport-independent validation for typed SEO mutations.
 */
final readonly class SeoMutationValidator
{
    /**
     * Create the mutation invariant validator.
     */
    public function __construct(
        private Factory $validation,
        private LocaleCatalog $locales,
    ) {}

    /**
     * Validate one complete profile mutation before persistence.
     *
     *
     * @param  array<string,mixed>  $payload
     */
    public function profile(array $payload): void
    {
        $this->validate($payload, self::profileRules());

        if (! array_key_exists('translations', $payload)) {
            return;
        }

        /** @var array<string,array<string,mixed>> $translations */
        $translations = $payload['translations'];
        foreach ($translations as $locale => $translation) {
            $this->assertTranslationSemantics($locale, $translation);
        }
    }

    /**
     * Validate one redirect mutation before persistence.
     *
     *
     * @param  array<string,mixed>  $payload
     */
    public function redirect(array $payload): void
    {
        $this->validate($payload, self::redirectRules());
        /** @var array{sourcePath:string,target:string,locale:?string,statusCode?:int,expectedRevision?:int|null} $payload */
        if ($payload['locale'] !== null) {
            try {
                $supported = $this->locales->supports($payload['locale']);
            } catch (InvalidLocaleException $exception) {
                throw InvalidSeoMutationException::forField(
                    'locale',
                    $exception->getMessage(),
                    $exception,
                );
            }

            if (! $supported) {
                throw InvalidSeoMutationException::forField(
                    'locale',
                    "The locale [{$payload['locale']}] is not supported.",
                );
            }
        }

        try {
            SeoPath::normalize($payload['sourcePath']);
            SeoRedirectTarget::normalize($payload['target']);
        } catch (InvalidArgumentException $exception) {
            throw InvalidSeoMutationException::because(
                $exception->getMessage(),
                previous: $exception,
            );
        }
    }

    /**
     * Validate a field map through the native mutation rules.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $rules
     */
    private function validate(array $values, array $rules): void
    {
        $unknown = array_diff(array_keys($values), array_keys($rules));
        if ($unknown !== []) {
            throw InvalidSeoMutationException::forField((string) reset($unknown), 'Unknown SEO mutation field.');
        }
        try {
            $this->validation->make($values, $rules)->validate();
        } catch (ValidationException $exception) {
            $errors = [];

            foreach ($exception->errors() as $field => $messages) {
                if (! is_string($field) || ! is_array($messages)) {
                    continue;
                }

                $normalizedMessages = [];

                foreach ($messages as $message) {
                    if (is_string($message)) {
                        $normalizedMessages[] = $message;
                    }
                }

                $errors[$field] = $normalizedMessages;
            }

            throw InvalidSeoMutationException::because(
                'The SEO mutation payload is invalid.',
                $errors,
                $exception,
            );
        }
    }

    /**
     * Validate path and URL semantics that Laravel's primitive rules cannot express.
     *
     * @param  array<string, mixed>  $translation
     */
    private function assertTranslationSemantics(string $locale, array $translation): void
    {
        try {
            $supported = $this->locales->supports($locale);
        } catch (InvalidLocaleException $exception) {
            throw InvalidSeoMutationException::forField(
                "translations.{$locale}",
                $exception->getMessage(),
                $exception,
            );
        }

        if (! $supported) {
            throw InvalidSeoMutationException::forField(
                "translations.{$locale}",
                "The locale [{$locale}] is not supported.",
            );
        }

        try {
            $path = $translation['path'] ?? null;

            if (is_string($path)) {
                SeoPath::normalize($path);
            }
        } catch (InvalidArgumentException $exception) {
            throw InvalidSeoMutationException::forField(
                "translations.{$locale}.path",
                $exception->getMessage(),
                $exception,
            );
        }

        $canonical = $translation['canonicalUrl']
            ?? $translation['canonical_url']
            ?? null;

        if (is_string($canonical) && ! HttpUrl::isCanonical($canonical)) {
            throw InvalidSeoMutationException::forField(
                "translations.{$locale}.canonicalUrl",
                'A canonical URL must be an absolute HTTP(S) URL without credentials or a fragment.',
            );
        }
    }

    /** @return array<string,mixed> */
    public static function profileRules(): array
    {
        return [
            'isIndexable' => ['sometimes', 'boolean'],
            'isFollowable' => ['sometimes', 'boolean'],
            'maxSnippet' => ['sometimes', 'nullable', 'integer', 'min:-1'],
            'maxImagePreview' => ['sometimes', 'nullable', Rule::in(['none', 'standard', 'large'])],
            'maxVideoPreview' => ['sometimes', 'nullable', 'integer', 'min:-1'],
            'sitemapIncluded' => ['sometimes', 'boolean'],
            'sitemapPriority' => ['sometimes', 'nullable', 'numeric', 'between:0,1'],
            'sitemapChangeFrequency' => [
                'sometimes',
                'nullable',
                Rule::enum(SitemapChangeFrequency::class),
            ],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'translations' => ['sometimes', 'array', new SupportedLocaleMapRule],
            'translations.*' => [
                'array:'.implode(',', array_keys(self::translationRules())),
            ],
            'expectedRevision' => ['sometimes', 'nullable', 'integer', 'min:0'],
            ...array_combine(array_map(static fn (string $field): string => 'translations.*.'.$field, array_keys(self::translationRules())), array_values(self::translationRules())),
        ];
    }

    /** @return array<string,mixed> */
    public static function translationRules(): array
    {
        return [
            'path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'canonicalUrl' => ['sometimes', 'nullable', 'string', 'max:2048', new CanonicalUrl],
            'imageUrl' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'imageReference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'imageAlt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'openGraphTitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'openGraphDescription' => ['sometimes', 'nullable', 'string', 'max:500'],
            'twitterTitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'twitterDescription' => ['sometimes', 'nullable', 'string', 'max:500'],
            'twitterCard' => ['sometimes', 'nullable', Rule::enum(TwitterCard::class)],
            'structuredData' => ['sometimes', 'nullable', 'array', new ValidStructuredData],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /** @return array<string,mixed> */
    public static function redirectRules(): array
    {
        return [
            'sourcePath' => ['required', 'string', 'max:2048', 'starts_with:/'],
            'target' => ['required', 'string', 'max:2048'],
            'locale' => ['nullable', 'string', 'max:35'],
            'statusCode' => ['integer', Rule::in([301, 302, 307, 308])],
            'isActive' => ['boolean'],
            'expiresAt' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
            'expectedRevision' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
