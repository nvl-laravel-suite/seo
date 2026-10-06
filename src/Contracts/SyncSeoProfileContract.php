<?php

declare(strict_types=1);

namespace Nvl\Seo\Contracts;

use Illuminate\Database\Eloquent\Model;
use Nvl\Seo\Data\Mutations\SeoProfilePayload;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Translatable\Enums\TranslationSyncMode;

/**
 * Defines the consumer-facing SyncSeoProfileAction workflow.
 *
 * @api
 */
interface SyncSeoProfileContract
{
    /**
     * Execute the execute workflow.
     */
    public function execute(
        Model $owner,
        SeoProfilePayload $data,
        ?string $scope = null,
        TranslationSyncMode $translationMode = TranslationSyncMode::Patch,
    ): SeoProfile;
}
