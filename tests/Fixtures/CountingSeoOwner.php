<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Counts constructions so tests can prove how often SEO re-normalises its registered hosts. */
final class CountingSeoOwner extends Model
{
    use HasUuids;

    public static int $constructed = 0;

    protected $table = 'seo_test_owners';

    /** @param  array<string, mixed>  $attributes */
    public function __construct(array $attributes = [])
    {
        self::$constructed++;
        parent::__construct($attributes);
    }
}
