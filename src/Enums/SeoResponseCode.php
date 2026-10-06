<?php

declare(strict_types=1);

namespace Nvl\Seo\Enums;

use Nvl\Support\Contracts\ResponseCode;

/** Stable public response discriminators for Seo.
 * @api
 */
enum SeoResponseCode: string implements ResponseCode
{
    case OperationFailed = 'operation_failed';
    case InvalidSeoMutation = 'invalid_seo_mutation';
    case SeoPathConflict = 'seo_path_conflict';
    case SeoRedirectLoop = 'seo_redirect_loop';
    case StaleSeoProfile = 'stale_seo_profile';
    case StaleSeoRedirect = 'stale_seo_redirect';
}
