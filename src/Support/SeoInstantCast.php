<?php

declare(strict_types=1);

namespace Nvl\Seo\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Stores SEO instants as UTC seconds with an explicit offset, independently of the host and database session timezone.
 *
 * A local wall clock is ambiguous inside a daylight-saving overlap, and PostgreSQL reads a `timestamptz` literal
 * without an offset in the session timezone, so either would lose the supplied instant. A stored string without an
 * offset is read as UTC.
 *
 * @implements CastsAttributes<CarbonImmutable, DateTimeInterface|string>
 *
 * @internal
 */
final class SeoInstantCast implements CastsAttributes
{
    /** Stored text format; one fixed width and offset keeps SQLite text comparisons chronological. */
    public const string FORMAT = 'Y-m-d H:i:sP';

    /** @param array<string, mixed> $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new InvalidArgumentException('SEO timestamps require UTC strings.');
        }

        return CarbonImmutable::parse($value, 'UTC');
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }
        $date = $value instanceof DateTimeInterface ? CarbonImmutable::instance($value) : CarbonImmutable::parse($value);

        return [$key => self::format($date)];
    }

    /** Format one instant exactly as it is stored, for writes and comparisons. */
    public static function format(DateTimeInterface $instant): string
    {
        return CarbonImmutable::instance($instant)->utc()->format(self::FORMAT);
    }
}
