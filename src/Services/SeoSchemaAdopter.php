<?php

declare(strict_types=1);

namespace Nvl\Seo\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\DatabaseManager;
use Nvl\Seo\Adoption\LegacySeoGraph;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Exceptions\InvalidSeoMutationException;
use Nvl\Support\Enums\SystemActor;

/**
 * Stages the legacy SEO graph with its original keys for a reviewed host cutover.
 *
 * @api
 *
 * @phpstan-import-type LegacySource from LegacySeoGraph
 *
 * @phpstan-type Review array{schema_fingerprint:string,fingerprint:string,profiles:int,translations:int,staged_profiles:int,staged_translations:int,conflicts:list<string>}
 */
final readonly class SeoSchemaAdopter
{
    private LegacySeoGraph $graph;

    /** Use native owner, translation and mutation policy at the adoption boundary, shared with the nvl:adopt adapter. */
    public function __construct(
        DatabaseManager $database,
        SeoOwnerRegistry $owners,
        SeoAuthorization $authorization,
        SeoMutationValidator $validator,
        SeoTranslationNormalizer $normalizer,
    ) {
        $this->graph = new LegacySeoGraph($database, $owners, $authorization, $validator, $normalizer);
    }

    /**
     * Inspect the quiescent legacy `seo` and `seo_i18n` tables without writing.
     * Accepts a source connection, target scope, and legacy morph-to-owner alias map.
     *
     * @param  array<array-key,mixed>  $source
     * @return Review
     */
    public function plan(Authenticatable|SystemActor $actor, array $source = []): array
    {
        return $this->inspect($actor, $this->admit($actor, $source));
    }

    /**
     * Stage only reviewed rows in committed profile/translation batches. Matching rows are reusable.
     *
     * @param  array<array-key,mixed>  $source
     * @return Review
     */
    public function apply(Authenticatable|SystemActor $actor, string $fingerprint, array $source = []): array
    {
        $options = $this->admit($actor, $source);
        $this->requireCommittedTarget();
        $review = $this->inspect($actor, $options);
        $this->assertReviewed($review, $fingerprint);
        if ($review['staged_profiles'] !== $review['profiles'] || $review['staged_translations'] !== $review['translations']) {
            $this->assertReviewed($this->inspect($actor, $options, write: true), $fingerprint);
            $review = $this->inspect($actor, $options);
        }

        return $this->assertReconciled($review, $fingerprint);
    }

    /**
     * Reconcile committed rows before the host activates native SEO.
     *
     * @param  array<array-key,mixed>  $source
     * @return Review
     */
    public function verify(Authenticatable|SystemActor $actor, string $fingerprint, array $source = []): array
    {
        $options = $this->admit($actor, $source);
        $this->requireCommittedTarget();

        return $this->assertReconciled($this->inspect($actor, $options), $fingerprint);
    }

    /**
     * @param  array<array-key,mixed>  $source
     * @return LegacySource
     */
    private function admit(Authenticatable|SystemActor $actor, array $source): array
    {
        return $this->graph->admit($actor, $source);
    }

    private function requireCommittedTarget(): void
    {
        if ($this->graph->target()->transactionLevel() !== 0) {
            throw InvalidSeoMutationException::because('Legacy SEO staging and verification require a committed target outside existing transactions.');
        }
    }

    /**
     * @param  LegacySource  $source
     * @return Review
     */
    private function inspect(Authenticatable|SystemActor $actor, array $source, bool $write = false): array
    {
        $target = $this->graph->target();
        $profileTable = $this->graph->profileTable();
        $translationTable = $this->graph->translationTable();
        $stage = static function (array $pendingProfiles, array $pendingTranslations) use ($target, $profileTable, $translationTable): void {
            $target->transaction(static function () use ($target, $profileTable, $translationTable, $pendingProfiles, $pendingTranslations): void {
                foreach ([$profileTable => $pendingProfiles, $translationTable => $pendingTranslations] as $table => $rows) {
                    foreach (array_chunk($rows, 40) as $batch) {
                        $target->table($table)->insert($batch);
                    }
                }
            });
        };
        $inspection = $this->graph->inspect($actor, $source, $write ? $stage : null);

        return [
            'schema_fingerprint' => $inspection['schema_fingerprint'], 'fingerprint' => $inspection['fingerprint'],
            'profiles' => $inspection['profiles'], 'translations' => $inspection['translations'],
            'staged_profiles' => $inspection['staged_profiles'], 'staged_translations' => $inspection['staged_translations'], 'conflicts' => $inspection['conflicts'],
        ];
    }

    /** @param Review $review */
    private function assertReviewed(array $review, string $fingerprint): void
    {
        if (! hash_equals($review['fingerprint'], $fingerprint)) {
            throw InvalidSeoMutationException::because('Legacy SEO schema or data changed. Review a new adoption plan.');
        }
        if ($review['conflicts'] !== []) {
            throw InvalidSeoMutationException::because('Legacy SEO has adoption conflicts. '.$review['conflicts'][0]);
        }
    }

    /**
     * @param  Review  $review
     * @return Review
     */
    private function assertReconciled(array $review, string $fingerprint): array
    {
        $this->assertReviewed($review, $fingerprint);
        if ($review['profiles'] !== $review['staged_profiles'] || $review['translations'] !== $review['staged_translations']) {
            throw InvalidSeoMutationException::because('Legacy SEO is not fully staged. Resume the reviewed adoption.');
        }

        return $review;
    }
}
