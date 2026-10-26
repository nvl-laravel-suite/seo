<?php

declare(strict_types=1);

namespace Nvl\Seo\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Nvl\Seo\Contracts\StructuredDataProvider;
use Nvl\Seo\ValueObjects\StructuredDataNode;

/**
 * Exercises configured resource-provider registration in package tests.
 */
final class TestStructuredDataProvider implements StructuredDataProvider
{
    /**
     * Return one generic resource node linked to the resolved page.
     *
     * @param  array{resourceType:string,resourceId:string,profileId:string,locale:string,scope:string,canonicalUrl:string|null,title:string|null,description:string|null,imageUrl:string|null,siteName:string,siteUrl:string}  $context
     * @return iterable<StructuredDataNode>
     */
    public function provide(
        Model $resource,
        array $context,
    ): iterable {
        yield StructuredDataNode::make(
            type: 'Thing',
            id: $context['canonicalUrl'].'#resource',
            properties: [
                'name' => $resource->getAttribute('name'),
                'url' => $context['canonicalUrl'],
                'mainEntityOfPage' => [
                    '@id' => $context['canonicalUrl'].'#webpage',
                ],
            ],
        );
    }
}
