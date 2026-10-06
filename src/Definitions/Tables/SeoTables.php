<?php

declare(strict_types=1);

namespace Nvl\Seo\Definitions\Tables;

use Nvl\Support\Config\PackageStorage;

/**
 * Canonical table names owned by the SEO package.
 */
final class SeoTables
{
    public const string Profiles = 'nvl_seo_profiles';

    public const string I18n = 'nvl_seo_i18n';

    public const string Redirects = 'nvl_seo_redirects';

    public const string RedirectLocks = 'nvl_seo_redirect_locks';

    /** Return one configured logical or historical package table. */
    public static function get(string $key): string
    {
        return PackageStorage::resolveTable('seo', $key);
    }
}
