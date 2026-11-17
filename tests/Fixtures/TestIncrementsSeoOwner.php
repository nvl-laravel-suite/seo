<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Nvl\Seo\Traits\HasSeo;

/**
 * Owner keyed by a 32-bit increments() column to verify key-width validation.
 */
final class TestIncrementsSeoOwner extends Model
{
    use HasSeo;

    protected $table = 'seo_test_increments_owners';

    /**
     * @var list<string>
     */
    protected $fillable = ['name'];
}
