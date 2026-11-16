<?php

declare(strict_types=1);

namespace Nvl\Seo\Adoption;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Connection;
use Nvl\Support\Adoption\AdoptionAdapter;
use Nvl\Support\Adoption\AdoptionBatch;
use Nvl\Support\Adoption\AdoptionException;
use Nvl\Support\Adoption\AdoptionPlan;
use Nvl\Support\Adoption\AdoptionStep;
use Nvl\Support\Adoption\AdoptionVerification;
use Nvl\Support\Enums\SystemActor;
use stdClass;

/**
 * Stages the legacy `seo` and `seo_i18n` graph through nvl:adopt: each batch commits legacy profiles with their translations.
 *
 * Options are the `SeoSchemaAdopter` source: `connection`, `scope` and `owner_aliases`. Owner aliases, scope admission
 * and owner and route collisions are checked across the whole graph before any batch is written.
 *
 * @internal Hosts reach it only through nvl:adopt.
 *
 * @phpstan-import-type LegacySource from LegacySeoGraph
 * @phpstan-import-type StagedRow from LegacySeoGraph
 */
final class SeoSchemaAdoptionAdapter extends AdoptionAdapter
{
    /** Bump when the mapping or batch row shape changes. */
    private const string Version = '1';

    private const string Step = 'profiles';

    /** @var LegacySource */
    private readonly array $source;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        Authenticatable|SystemActor $actor,
        array $options,
        private readonly LegacySeoGraph $graph,
    ) {
        parent::__construct($actor, $options);
        $this->source = $graph->source($options);
    }

    public function connection(): Connection
    {
        return $this->graph->target();
    }

    /**
     * Every phase requires the SEO import ability in the target scope; plan and verify also authorize each owner.
     */
    public function authorize(string $phase): void
    {
        $this->graph->admit($this->actor, $this->options);
    }

    public function plan(): AdoptionPlan
    {
        $inspection = $this->graph->inspect($this->actor, $this->source);
        $origin = $this->graph->origin($this->source);
        $target = $this->graph->target();

        return new AdoptionPlan(
            package: 'seo',
            adapterVersion: self::Version,
            fingerprint: AdoptionPlan::digest(['adapter' => self::Version, 'graph' => $inspection['fingerprint']]),
            source: ['connection' => $origin->getName(), 'profiles' => 'seo', 'translations' => 'seo_i18n'],
            destination: [
                'connection' => $target->getName(), 'scope' => $this->source['scope'],
                'profiles' => $this->graph->profileTable(), 'translations' => $this->graph->translationTable(),
            ],
            optionsDigest: AdoptionPlan::digest($this->options),
            steps: [new AdoptionStep(self::Step)],
            expected: [self::Step => $inspection['profiles']],
            collisions: $inspection['conflicts'],
        );
    }

    /**
     * Map one keyset page of legacy profiles; each row carries a profile and all of its translations.
     */
    public function nextBatch(AdoptionStep $step, ?array $cursor, int $limit): AdoptionBatch
    {
        if ($step->id !== self::Step) {
            throw new AdoptionException("SEO adoption has no step [{$step->id}].");
        }
        $origin = $this->graph->origin($this->source);
        /** @var list<stdClass> $read */
        $read = $step->page($origin->table('seo')->useWritePdo(), $cursor, $limit + 1)->get()->all();
        $page = array_slice($read, 0, $limit);
        $translations = $this->graph->legacyTranslations($origin, $page);
        $rows = [];
        foreach ($page as $legacy) {
            $profile = $this->graph->profile($this->actor, $legacy, $this->source);
            $rows[] = [
                'profile' => $profile,
                'translations' => array_values($translations->get($profile['id'], collect())
                    ->map(fn (stdClass $translation): array => $this->graph->translation($translation, $profile['id'], $this->source['scope']))
                    ->all()),
            ];
        }
        $last = $page === [] ? null : $page[array_key_last($page)];

        return new AdoptionBatch(self::Step, $cursor, $last === null ? $cursor : $step->cursorFrom($last), $rows, count($read) <= $limit);
    }

    /**
     * Insert the profiles of one batch before their translations, reusing matching rows and refusing differing ones.
     */
    public function writeBatch(AdoptionBatch $batch): ?string
    {
        $profiles = $translations = [];
        foreach ($batch->rows as $row) {
            $profiles[] = $this->stagedRow($row['profile'] ?? null);
            foreach (is_array($row['translations'] ?? null) ? $row['translations'] : [] as $translation) {
                $translations[] = $this->stagedRow($translation);
            }
        }
        $target = $this->graph->target();
        $conflicts = [];
        $pending = [
            $this->graph->profileTable() => $this->graph->pending($target, $this->graph->profileTable(), $profiles, $conflicts),
            $this->graph->translationTable() => $this->graph->pending($target, $this->graph->translationTable(), $translations, $conflicts),
        ];
        if ($conflicts !== []) {
            throw new AdoptionException('Adoption drift behind the cursor: '.$conflicts[0]);
        }
        foreach ($pending as $table => $rows) {
            foreach (array_chunk($rows, 40) as $chunk) {
                $target->table($table)->insert($chunk);
            }
        }

        return null;
    }

    /**
     * Rescan the committed legacy graph against the native scope, independently of checkpoints.
     */
    public function verify(AdoptionPlan $plan): AdoptionVerification
    {
        $inspection = $this->graph->inspect($this->actor, $this->source);

        return new AdoptionVerification(
            missing: $inspection['profiles'] - $inspection['staged_profiles'] + $inspection['translations'] - $inspection['staged_translations'],
            extra: $inspection['extra'],
            changed: $inspection['changed'],
            digest: AdoptionPlan::digest(['graph' => $inspection['fingerprint'], 'staged' => $inspection['digest']]),
            problems: $inspection['collisions'],
        );
    }

    /**
     * @return StagedRow
     */
    private function stagedRow(mixed $row): array
    {
        if (! is_array($row) || ! is_string($row['id'] ?? null)) {
            throw new AdoptionException('SEO adoption batches require mapped rows with string ids.');
        }
        $staged = [];
        foreach ($row as $field => $value) {
            if (! is_string($field)) {
                throw new AdoptionException('SEO adoption batches require mapped rows with named columns.');
            }
            $staged[$field] = $value;
        }
        $staged['id'] = $row['id'];

        return $staged;
    }
}
