<?php

declare(strict_types=1);

namespace Nvl\Seo\Contracts;

/**
 * Consumer-owned, cursor-paginated source for neutral profile adoption.
 *
 * @api
 */
interface SeoImportSource
{
    /** @return array{items:list<array{ownerAlias:string,ownerId:string,scope:string|null,profile:array<string,mixed>}>,nextCursor:string|null} */
    public function page(?string $cursor, int $limit): array;
}
