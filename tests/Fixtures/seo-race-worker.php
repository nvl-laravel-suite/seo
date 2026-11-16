<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Nvl\Seo\Exceptions\SeoPathConflictException;
use Nvl\Seo\Exceptions\SeoRedirectLoopException;
use Nvl\Seo\Exceptions\StaleSeoProfileException;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Models\SeoProfileTranslation;
use Nvl\Seo\Models\SeoRedirect;
use Nvl\Seo\Providers\SeoServiceProvider;
use Nvl\Seo\Services\SeoProfiles;
use Nvl\Seo\Services\SeoRedirects;
use Nvl\Seo\Tests\Fixtures\SeoRaceWorkerProvider;
use Nvl\Seo\Tests\Fixtures\TestSeoOwner;
use Nvl\Support\Enums\SystemActor;
use Nvl\Support\Providers\LocaleServiceProvider;
use Nvl\Support\Providers\SupportServiceProvider;
use Nvl\Translatable\Providers\TranslatableServiceProvider;
use Orchestra\Testbench\Foundation\Application;

require dirname(__DIR__, 5).'/vendor/autoload.php';

$source = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$options = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);
if (! is_array($source) || ($source['driver'] ?? null) !== 'pgsql'
    || ! is_array($options) || ! is_string($options['directory'] ?? null) || ! is_dir($options['directory'])
    || preg_match('/^nvl-seo-race-[a-f0-9]{16}$/D', basename($options['directory'])) !== 1
    || ! in_array($options['role'] ?? null, ['holder', 'waiter'], true)
    || ! in_array($options['operation'] ?? null, ['profile', 'path', 'redirect'], true) || ! is_string($options['owner'] ?? null)) {
    throw new RuntimeException('SEO race workers require the committed PostgreSQL source and barrier directory.');
}

/** Wait for one barrier file written by the parent test. */
$await = static function (string $path): void {
    $deadline = microtime(true) + 15;
    while (! is_file($path)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException("The SEO race barrier [{$path}] timed out.");
        }
        usleep(10000);
        clearstatcache();
    }
};
/** Publish one JSON barrier file atomically. */
$signal = static function (string $path, array $payload): void {
    file_put_contents($path.'.pending', json_encode($payload, JSON_THROW_ON_ERROR));
    rename($path.'.pending', $path);
};

$configuration = [];
foreach ($source as $key => $value) {
    if (is_string($key)) {
        $configuration[$key] = $value;
    }
}
SeoRaceWorkerProvider::$source = $configuration;
$app = Application::create(options: ['load_environment_variables' => false, 'extra' => [
    'dont-discover' => ['*'],
    'providers' => [
        SeoRaceWorkerProvider::class, LocaleServiceProvider::class, SupportServiceProvider::class,
        TranslatableServiceProvider::class, SeoServiceProvider::class,
    ],
]]);
try {
    $app->make(Kernel::class)->bootstrap();
    $physical = $app->make('db')->connection()->selectOne('select current_database() as database, pg_backend_pid() as pid');
    $pid = is_object($physical) ? filter_var($physical->pid ?? null, FILTER_VALIDATE_INT) : false;
    if (! is_object($physical) || ! is_string($physical->database ?? null) || ! is_int($pid)) {
        throw new RuntimeException('SEO race workers require a physical PostgreSQL session identity.');
    }
    $identity = ['database' => $physical->database, 'pid' => $pid];
    $directory = $options['directory'];
    $role = $options['role'];

    if ($role === 'holder') {
        // Hold the first written row uncommitted until the parent has seen the waiter blocked behind it.
        $held = false;
        $model = match ($options['operation']) {
            'profile' => SeoProfile::class,
            'path' => SeoProfileTranslation::class,
            'redirect' => SeoRedirect::class,
        };
        $model::created(static function () use (&$held, $signal, $await, $directory, $identity): void {
            if (! $held) {
                $held = true;
                $signal($directory.'/holding', $identity);
                $await($directory.'/release');
            }
        });
    } else {
        // Pause at the first authorization decision, before the waiter writes anything.
        $ready = false;
        SeoRaceWorkerProvider::$onAuthorize = static function () use (&$ready, $signal, $await, $directory, $identity): void {
            if (! $ready) {
                $ready = true;
                $signal($directory.'/ready', $identity);
                $await($directory.'/go');
            }
        };
    }

    try {
        if ($options['operation'] === 'redirect') {
            $app->make(SeoRedirects::class)->sync(SystemActor::Background, null, $role === 'holder'
                ? ['sourcePath' => '/race-a', 'target' => '/race-b']
                : ['sourcePath' => '/race-b', 'target' => '/race-a']);
        } else {
            $translation = ['title' => $role, ...($options['operation'] === 'path' ? ['path' => '/race-shared'] : [])];
            $app->make(SeoProfiles::class)->sync(SystemActor::Background, TestSeoOwner::query()->findOrFail($options['owner']), ['translations' => ['en' => $translation]]);
        }
        $outcome = ['status' => 'written'];
    } catch (StaleSeoProfileException|SeoPathConflictException|SeoRedirectLoopException $exception) {
        $outcome = ['status' => 'rejected', 'exception' => $exception::class];
    }

    fwrite(STDOUT, json_encode([...$outcome, ...$identity], JSON_THROW_ON_ERROR));
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n".$exception->getTraceAsString()."\n");
    exit(1);
}
