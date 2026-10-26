<?php

declare(strict_types=1);

namespace Nvl\Seo\Contracts;

use Illuminate\Database\Eloquent\Model;
use Nvl\Seo\ValueObjects\StructuredDataNode;

/**
 * Builds schema.org nodes for one registered Eloquent resource family.
 *
 * @api
 *
 * @phpstan-type StructuredContext array{resourceType:string,resourceId:string,profileId:string,locale:string,scope:string,canonicalUrl:string|null,title:string|null,description:string|null,imageUrl:string|null,siteName:string,siteUrl:string}
 */
interface StructuredDataProvider
{
    /**
     * Return structured-data nodes that accurately describe the visible resource.
     *
     * @param  StructuredContext  $context
     * @return iterable<StructuredDataNode>
     */
    public function provide(
        Model $resource,
        array $context,
    ): iterable;
}
