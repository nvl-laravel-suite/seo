<?php

declare(strict_types=1);

namespace Nvl\Seo\Actions;

use InvalidArgumentException;
use Nvl\Seo\Models\SeoRedirect;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;
use Nvl\Support\Ownership\Services\OwnerSiteContext;

/**
 * Permanently removes soft-deleted redirects after a retention period.
 *
 * @internal
 */
final class PruneSeoRedirectsAction
{
    /**
     * Prune redirects deleted at least the requested number of days ago.
     */
    public function execute(int $retentionDays = 30): int
    {
        if ($retentionDays < 1) {
            throw new InvalidArgumentException('SEO redirect retention must be at least one day.');
        }

        if (OwnerSiteContext::current() !== null) {
            throw new OwnershipViolation('Public site admission cannot prune retained redirects.');
        }
        $connection = (new SeoRedirect)->getConnection();
        $deletedBefore = now()->subDays($retentionDays);

        return $connection->transaction(
            static fn (): int => SeoRedirect::onlyTrashed()
                ->where('deleted_at', '<=', $deletedBefore)
                ->toBase()->delete(),
        );
    }
}
