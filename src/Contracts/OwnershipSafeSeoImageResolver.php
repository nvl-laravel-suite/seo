<?php

declare(strict_types=1);

namespace Nvl\Seo\Contracts;

use Nvl\Seo\Support\SeoImageContext;

/**
 * Authorizes a canonical owner-local reference before a social image can be resolved.
 *
 * @api
 */
interface OwnershipSafeSeoImageResolver extends SeoImageResolver
{
    /** Validate the actual reference against its registered native ownership boundary. */
    public function assertOwnershipSafe(SeoImageContext $context): void;
}
