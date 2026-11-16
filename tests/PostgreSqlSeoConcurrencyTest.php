<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Nvl\Seo\Exceptions\SeoPathConflictException;
use Nvl\Seo\Exceptions\SeoRedirectLoopException;
use Nvl\Seo\Exceptions\StaleSeoProfileException;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Models\SeoProfileTranslation;
use Nvl\Seo\Models\SeoRedirect;
use Nvl\Seo\Tests\Fixtures\TestSeoOwner;
use Nvl\Seo\Tests\TestCase;
use Symfony\Component\Process\Process;

uses(TestCase::class);

/**
 * Run a holder and a waiter worker against the committed fixture: the holder keeps its first row uncommitted until the
 * waiter is proven blocked on it.
 *
 * @param  array{holder: string, waiter: string}  $owners
 * @return array{holder: array<string, mixed>, waiter: array<string, mixed>}
 */
function raceSeoWorkers(string $operation, array $owners): array
{
    $connection = DB::connection();
    $source = $connection->getConfig();
    $parent = (array) $connection->selectOne('select current_database() as database, pg_backend_pid() as pid');
    $directory = sys_get_temp_dir().'/nvl-seo-race-'.bin2hex(random_bytes(8));
    if (! mkdir($directory, 0700)) {
        throw new RuntimeException('Cannot create the SEO race barrier directory.');
    }
    $workers = [];
    $await = static function (string $path) use (&$workers): array {
        $deadline = microtime(true) + 15;
        while (! is_file($path)) {
            foreach ($workers as $worker) {
                $worker->checkTimeout();
                if (! $worker->isRunning()) {
                    throw new RuntimeException('An SEO race worker exited early with code ['.$worker->getExitCode().']: '.$worker->getErrorOutput().$worker->getOutput());
                }
            }
            if (microtime(true) >= $deadline) {
                throw new RuntimeException("The SEO race barrier [{$path}] timed out.");
            }
            usleep(10000);
            clearstatcache();
        }

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    };

    try {
        foreach (['holder', 'waiter'] as $role) {
            $worker = new Process([
                PHP_BINARY,
                __DIR__.'/Fixtures/seo-race-worker.php',
                json_encode($source, JSON_THROW_ON_ERROR),
                json_encode(['directory' => $directory, 'role' => $role, 'operation' => $operation, 'owner' => $owners[$role]], JSON_THROW_ON_ERROR),
            ], env: ['XDEBUG_MODE' => 'off'], timeout: 40);
            $worker->start();
            $workers[$role] = $worker;
        }
        $holder = $await($directory.'/holding');
        $waiter = $await($directory.'/ready');
        expect($holder['database'])->toBe($parent['database'])
            ->and($waiter['database'])->toBe($parent['database'])
            ->and(array_unique([(int) $parent['pid'], $holder['pid'], $waiter['pid']]))->toHaveCount(3);

        touch($directory.'/go');
        $blocked = false;
        $deadline = microtime(true) + 15;
        while (! $blocked && microtime(true) < $deadline) {
            $blockers = $connection->selectOne('select pg_blocking_pids(?)::text as pids', [$waiter['pid']])->pids;
            $blocked = in_array($holder['pid'], array_map(intval(...), explode(',', trim((string) $blockers, '{}'))), true);
            usleep(20000);
        }
        expect($blocked)->toBeTrue("The {$operation} waiter never blocked on the holder's uncommitted write.");
        touch($directory.'/release');

        $outcomes = [];
        foreach ($workers as $role => $worker) {
            $worker->wait();
            expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput());
            $outcomes[$role] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }

        return $outcomes;
    } finally {
        foreach ($workers as $worker) {
            $worker->stop();
        }
        foreach (glob($directory.'/*') ?: [] as $path) {
            unlink($path);
        }
        rmdir($directory);
    }
}

beforeEach(function (): void {
    if (getenv('NVL_OWNERSHIP_TEST_DRIVER') !== 'pgsql' || DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Requires the isolated committed PostgreSQL package database.');
    }
});

afterEach(function (): void {
    if (DB::connection()->getDriverName() === 'pgsql' && DB::connection()->transactionLevel() === 0) {
        SeoProfileTranslation::query()->delete();
        SeoProfile::query()->delete();
        SeoRedirect::query()->withTrashed()->forceDelete();
        TestSeoOwner::query()->withTrashed()->forceDelete();
    }
});

it('serializes initial profiles', function (): void {
    $owner = (string) TestSeoOwner::query()->create(['name' => 'Raced owner'])->getKey();
    $this->commitDatabaseFixture();

    $outcomes = raceSeoWorkers('profile', ['holder' => $owner, 'waiter' => $owner]);

    expect($outcomes['holder']['status'])->toBe('written')
        ->and($outcomes['waiter'])->toMatchArray(['status' => 'rejected', 'exception' => StaleSeoProfileException::class])
        ->and(SeoProfile::query()->where('seoable_id', $owner)->sole()->revision)->toBe(1)
        ->and(SeoProfileTranslation::query()->sole()->title)->toBe('holder');
});

it('arbitrates identical paths', function (): void {
    $holder = (string) TestSeoOwner::query()->create(['name' => 'Path holder'])->getKey();
    $waiter = (string) TestSeoOwner::query()->create(['name' => 'Path waiter'])->getKey();
    $this->commitDatabaseFixture();

    $outcomes = raceSeoWorkers('path', ['holder' => $holder, 'waiter' => $waiter]);
    $translation = SeoProfileTranslation::query()->where('path', '/race-shared')->sole();

    expect($outcomes['holder']['status'])->toBe('written')
        ->and($outcomes['waiter'])->toMatchArray(['status' => 'rejected', 'exception' => SeoPathConflictException::class])
        ->and($translation->title)->toBe('holder')
        ->and(SeoProfile::query()->where('seoable_id', $waiter)->exists())->toBeFalse();
});

it('prevents opposing redirect edges', function (): void {
    $owner = (string) TestSeoOwner::query()->create(['name' => 'Redirect anchor'])->getKey();
    $this->commitDatabaseFixture();

    $outcomes = raceSeoWorkers('redirect', ['holder' => $owner, 'waiter' => $owner]);

    expect($outcomes['holder']['status'])->toBe('written')
        ->and($outcomes['waiter'])->toMatchArray(['status' => 'rejected', 'exception' => SeoRedirectLoopException::class])
        ->and(SeoRedirect::query()->get(['source_path', 'target'])->map->only(['source_path', 'target'])->all())
        ->toBe([['source_path' => '/race-a', 'target' => '/race-b']]);
});
