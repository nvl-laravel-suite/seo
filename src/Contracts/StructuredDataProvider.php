<?php

declare(strict_types=1);

namespace Nvl\Seo\Contracts;

use Illuminate\Database\Eloquent\Model;
use Nvl\Seo\ValueObjects\StructuredDataNode;

/**
 * Builds schema.org nodes for one registered Eloquent resource family.
 *
 * @api
 */
interface StructuredDataProvider
{
    /**
     * Return structured-data nodes that accurately describe the visible resource.
     *
     * @param  array{resourceType:string,resourceId:string,profileId:string,locale:string,scope:string,canonicalUrl:string|null,title:string|null,description:string|null,imageUrl:string|null,siteName:string,siteUrl:string}  $context
     * @return iterable<StructuredDataNode>
     */
    public function provide(
        Model $resource,
        array $context,
    ): iterable;
}
