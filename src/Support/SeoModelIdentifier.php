<?php

declare(strict_types=1);

namespace Nvl\Seo\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Normalizes Eloquent keys for the package's string-compatible owner columns.
 */
final class SeoModelIdentifier
{
    /**
     * Normalize one string-compatible Eloquent identifier.
     */
    public static function normalize(string|int $identifier): string
    {
        $identifier = (string) $identifier;

        if (trim($identifier) === '') {
            throw new InvalidArgumentException(
                'An SEO owner identifier must not be empty.',
            );
        }

        if (mb_strlen($identifier) > 255) {
            throw new InvalidArgumentException(
                'An SEO owner identifier may not exceed 255 characters.',
            );
        }

        return $identifier;
    }

    /**
     * Treat a malformed package identifier as absent; PostgreSQL rejects a non-UUID value compared with a UUID key.
     *
     * @param  class-string<Model>  $model
     */
    public static function uuid(string $identifier, string $model): string
    {
        if (! Str::isUuid($identifier)) {
            throw (new ModelNotFoundException)->setModel($model, [$identifier]);
        }

        return $identifier;
    }

    /**
     * Return the normalized identifier of one persisted owner.
     */
    public static function required(Model $model): string
    {
        $identifier = $model->getKey();

        if (! is_string($identifier) && ! is_int($identifier)) {
            throw new InvalidArgumentException(
                'The SEO owner ['.$model::class.'] must use a string- or integer-compatible key.',
            );
        }

        try {
            return self::normalize($identifier);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException(
                'The SEO owner ['.$model::class.'] must have a non-empty identifier of at most 255 characters.',
                previous: $exception,
            );
        }
    }

    private function __construct() {}
}
