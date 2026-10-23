<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Nvl\Seo\Traits\HasSeo;

/**
 * Case-sensitive host fixture with exact custom string owner identifiers.
 *
 * @property string $id The native host key.
 * @property string $name The owner display name.
 * @property Carbon|null $created_at The creation timestamp.
 * @property Carbon|null $updated_at The update timestamp.
 */
final class TestStringSeoOwner extends Model
{
    use HasSeo;

    /** @var bool */
    public $incrementing = false;

    /** @var string */
    protected $table = 'seo_test_string_owners';

    /** @var string */
    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = ['id', 'name'];
}
