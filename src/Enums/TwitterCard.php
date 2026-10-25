<?php

declare(strict_types=1);

namespace Nvl\Seo\Enums;

/**
 * Supported X/Twitter card presentation modes.
 *
 * @api
 */
enum TwitterCard: string
{
    case Summary = 'summary';
    case SummaryLargeImage = 'summary_large_image';
    case App = 'app';
    case Player = 'player';
}
