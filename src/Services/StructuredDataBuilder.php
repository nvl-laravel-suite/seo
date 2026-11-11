<?php

declare(strict_types=1);

namespace Nvl\Seo\Services;

use InvalidArgumentException;
use Nvl\Seo\Enums\StructuredDataType;
use Nvl\Seo\Support\HttpUrl;
use Nvl\Seo\Support\StructuredDataLimits;
use Nvl\Seo\ValueObjects\StructuredDataNode;

/**
 * Builds safe schema.org JSON-LD object shapes without presentation markup.
 *
 * @api
 */
final class StructuredDataBuilder
{
    /**
     * Build one typed, context-free schema.org node for provider composition.
     *
     * @param  array<string, mixed>  $properties
     */
    public function node(
        string|StructuredDataType $type,
        array $properties = [],
        ?string $id = null,
    ): StructuredDataNode {
        return StructuredDataNode::make($type, $properties, $id);
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    public function schema(string|StructuredDataType $type, array $properties): array
    {
        return [
            '@context' => 'https://schema.org',
            ...$this->node($type, $properties)->toJsonLd(),
        ];
    }

    /**
     * Build a shared-context JSON-LD graph from schema.org node objects.
     *
     * @param  list<array<string, mixed>|StructuredDataNode>  $nodes
     * @return array<string, mixed>
     */
    public function graph(array $nodes): array
    {
        if ($nodes === []) {
            throw new InvalidArgumentException('A structured-data graph requires at least one node.');
        }

        $graph = [];

        foreach ($nodes as $node) {
            $value = $node instanceof StructuredDataNode
                ? $node->toJsonLd()
                : $node;
            unset($value['@context']);
            $graph[] = $value;
        }

        $document = [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
        StructuredDataLimits::assert($document);

        return $document;
    }

    /**
     * Build a JSON-LD node reference using a stable identifier.
     *
     * @return array{'@id': string}
     */
    public function reference(string $id): array
    {
        if (! HttpUrl::isAbsolute($id)
            && preg_match('/^(?:#|urn:)[^\s]+$/D', $id) !== 1) {
            throw new InvalidArgumentException(
                'Structured-data references must use an absolute HTTP URL, fragment, or URN.',
            );
        }

        return ['@id' => $id];
    }

    /**
     * @param  list<array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    public function breadcrumbs(array $items): array
    {
        $elements = [];

        foreach ($items as $index => $item) {
            if (
                trim($item['name']) === ''
                || ! HttpUrl::isAbsolute($item['url'])
            ) {
                throw new InvalidArgumentException(
                    'Every breadcrumb requires a name and an absolute HTTP or HTTPS URL.',
                );
            }

            $elements[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ];
        }

        if ($elements === []) {
            throw new InvalidArgumentException('Breadcrumbs require at least one item.');
        }

        return $this->schema(
            StructuredDataType::BreadcrumbList,
            ['itemListElement' => $elements],
        );
    }
}
