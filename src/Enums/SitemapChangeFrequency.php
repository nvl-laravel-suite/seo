<?php

declare(strict_types=1);

namespace Nvl\Seo\Enums;

/**
 * Standard sitemap change-frequency hints.
 *
 * @api
 */
enum SitemapChangeFrequency: string
{
    case Always = 'always';
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Never = 'never';
}
