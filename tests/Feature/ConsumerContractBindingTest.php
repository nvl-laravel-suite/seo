<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Nvl\Seo\Actions\ArchiveSeoProfileAction;
use Nvl\Seo\Actions\DeleteSeoProfileAction;
use Nvl\Seo\Actions\DuplicateSeoProfileAction;
use Nvl\Seo\Actions\GetOwnerSeoProfileAction;
use Nvl\Seo\Actions\GetOwnerSeoRevisionAction;
use Nvl\Seo\Actions\GetSeoProfileAction;
use Nvl\Seo\Actions\ImportSeoProfilesAction;
use Nvl\Seo\Actions\ListOwnerSeoProfilesAction;
use Nvl\Seo\Actions\ListSeoProfilesAction;
use Nvl\Seo\Actions\PreviewSeoProfileAction;
use Nvl\Seo\Actions\SeoProfileStatusAction;
use Nvl\Seo\Actions\SyncSeoProfileAction;
use Nvl\Seo\Actions\SyncSeoRedirectAction;
use Nvl\Seo\Contracts\ArchiveSeoProfileContract;
use Nvl\Seo\Contracts\DeleteSeoProfileContract;
use Nvl\Seo\Contracts\DuplicateSeoProfileContract;
use Nvl\Seo\Contracts\GetOwnerSeoProfileContract;
use Nvl\Seo\Contracts\GetOwnerSeoRevisionContract;
use Nvl\Seo\Contracts\GetSeoProfileContract;
use Nvl\Seo\Contracts\ImportSeoProfilesContract;
use Nvl\Seo\Contracts\ListOwnerSeoProfilesContract;
use Nvl\Seo\Contracts\ListSeoProfilesContract;
use Nvl\Seo\Contracts\PreviewSeoProfileContract;
use Nvl\Seo\Contracts\SeoProfileStatusContract;
use Nvl\Seo\Contracts\SyncSeoProfileContract;
use Nvl\Seo\Contracts\SyncSeoRedirectContract;
use Nvl\Seo\Providers\SeoServiceProvider;
use Nvl\Seo\Tests\TestCase;

if (! in_array(dirname(__DIR__).'/Pest.php', get_included_files(), true)) {
    uses(TestCase::class);
}

/** @return list<array{class-string, class-string}> */
function nvlConsumerBindingsForSeo(): array
{
    return [
        [ArchiveSeoProfileContract::class, ArchiveSeoProfileAction::class],
        [DeleteSeoProfileContract::class, DeleteSeoProfileAction::class],
        [DuplicateSeoProfileContract::class, DuplicateSeoProfileAction::class],
        [GetOwnerSeoProfileContract::class, GetOwnerSeoProfileAction::class],
        [GetOwnerSeoRevisionContract::class, GetOwnerSeoRevisionAction::class],
        [GetSeoProfileContract::class, GetSeoProfileAction::class],
        [ImportSeoProfilesContract::class, ImportSeoProfilesAction::class],
        [ListOwnerSeoProfilesContract::class, ListOwnerSeoProfilesAction::class],
        [ListSeoProfilesContract::class, ListSeoProfilesAction::class],
        [PreviewSeoProfileContract::class, PreviewSeoProfileAction::class],
        [SeoProfileStatusContract::class, SeoProfileStatusAction::class],
        [SyncSeoProfileContract::class, SyncSeoProfileAction::class],
        [SyncSeoRedirectContract::class, SyncSeoRedirectAction::class],
    ];
}

test('published workflow contracts retain native signatures attributes and generic documentation', function (): void {
    $genericDocumentation = static function (string|false $documentation): array {
        if ($documentation === false) {
            return [];
        }
        preg_match_all('/@param\s+([^\r\n]+?)\s+(\$[A-Za-z_][A-Za-z0-9_]*)\b/', $documentation, $parameters, PREG_SET_ORDER);
        $result = [];
        foreach ($parameters as $parameter) {
            $type = preg_replace('/\s+/', '', $parameter[1]);
            if (str_contains($type, '<') || str_contains($type, '{') || str_contains($type, '[]')) {
                $result['@param'.$parameter[2]] = $type;
            }
        }
        if (preg_match('/@return\s+([^\r\n]+)/', $documentation, $return) === 1) {
            $type = '';
            $depth = 0;
            foreach (str_split($return[1]) as $character) {
                if (preg_match('/\s/', $character) === 1 && $depth === 0) {
                    break;
                }
                if (str_contains('<{([', $character)) {
                    $depth++;
                } elseif (str_contains('>})]', $character)) {
                    $depth--;
                }
                if (preg_match('/\s/', $character) !== 1) {
                    $type .= $character;
                }
            }
            if (str_contains($type, '<') || str_contains($type, '{') || str_contains($type, '[]')) {
                $result['@return'] = $type;
            }
        }

        return $result;
    };

    foreach (nvlConsumerBindingsForSeo() as [$contract, $implementation]) {
        $interface = new ReflectionClass($contract);
        $concrete = new ReflectionClass($implementation);
        expect($interface->isInterface())->toBeTrue()
            ->and($concrete->implementsInterface($contract))->toBeTrue();
        foreach ($interface->getMethods() as $method) {
            $native = $concrete->getMethod($method->getName());
            $return = (string) $method->getReturnType();

            $publishedTypes = $genericDocumentation($method->getDocComment());
            foreach ($genericDocumentation($native->getDocComment()) as $tag => $type) {
                expect($publishedTypes[$tag] ?? null)->toBe($type);
            }

            expect($native->isPublic())->toBeTrue()
                ->and($native->isStatic())->toBeFalse()
                ->and(count($method->getParameters()))->toBe(count($native->getParameters()));
            if ($return !== 'self') {
                expect((string) $native->getReturnType())->toBe($return);
            } else {
                $nativeReturn = (string) $native->getReturnType();
                expect(is_a(in_array($nativeReturn, ['self', 'static'], true) ? $native->getDeclaringClass()->getName() : $nativeReturn, $contract, true))->toBeTrue();
            }
            foreach ($method->getParameters() as $position => $parameter) {
                $actual = $native->getParameters()[$position];
                expect($actual->getName())->toBe($parameter->getName())
                    ->and((string) $actual->getType())->toBe((string) $parameter->getType())
                    ->and($actual->isVariadic())->toBe($parameter->isVariadic())
                    ->and($actual->isPassedByReference())->toBe($parameter->isPassedByReference())
                    ->and($actual->isDefaultValueAvailable())->toBe($parameter->isDefaultValueAvailable())
                    ->and(array_map(static fn (ReflectionAttribute $attribute): array => [$attribute->getName(), $attribute->getArguments()], $actual->getAttributes()))
                    ->toBe(array_map(static fn (ReflectionAttribute $attribute): array => [$attribute->getName(), $attribute->getArguments()], $parameter->getAttributes()));
                if ($parameter->isDefaultValueAvailable()) {
                    expect($actual->getDefaultValue())->toEqual($parameter->getDefaultValue());
                }
            }
        }
    }
});

test('native provider defaults resolve each workflow while preserving late host substitutes', function (): void {
    foreach (nvlConsumerBindingsForSeo() as [$contract, $implementation]) {
        expect($this->app->bound($contract))->toBeTrue()
            ->and($this->app->make($contract))->toBeInstanceOf($implementation);
        $host = Mockery::mock($contract);
        $this->app->instance($contract, $host);
        expect($this->app->make($contract))->toBe($host);
    }
});

test('provider registration preserves early interface bindings in a second native application', function (): void {
    $consumer = new Application($this->app->basePath());
    $consumer->instance('config', new Repository($this->app->make('config')->all()));
    $consumer->instance('env', 'testing');
    $consumer->register(FilesystemServiceProvider::class);
    $hosts = [];
    foreach (nvlConsumerBindingsForSeo() as [$contract]) {
        $hosts[$contract] = Mockery::mock($contract);
        $consumer->instance($contract, $hosts[$contract]);
    }
    try {
        $consumer->register(SeoServiceProvider::class);
        foreach ($hosts as $contract => $host) {
            expect($consumer->make($contract))->toBe($host);
        }
    } finally {
        Container::setInstance($this->app);
        $consumer->flush();
    }
});
