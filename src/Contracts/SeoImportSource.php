<?php

declare(strict_types=1);

namespace Nvl\Seo\Contracts;

/**
 * Consumer-owned, cursor-paginated source for neutral profile adoption.
 *
 * @api
 *
 * @phpstan-import-type ProfileMutation from \Nvl\Seo\Services\SeoProfiles
 */
interface SeoImportSource
{
    /** @return array{items:list<array{ownerAlias:string,ownerId:string,scope:string|null,profile:ProfileMutation}>,nextCursor:string|null} */
    public function page(?string $cursor, int $limit): array;
}
