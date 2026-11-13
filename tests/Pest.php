<?php

declare(strict_types=1);

use Nvl\Ownership\Tests\Fixtures\ReusesAdoptedSchema;
use Nvl\Seo\Tests\LegacyOwnershipTestCase;
use Nvl\Seo\Tests\OwnershipTestCase;
use Nvl\Seo\Tests\TestCase;

uses(TestCase::class)->in(__DIR__.'/Feature');

if (trait_exists(ReusesAdoptedSchema::class)) {
    pest()->extend(OwnershipTestCase::class)->in(__DIR__.'/Ownership');
    pest()->extend(LegacyOwnershipTestCase::class)->in(__DIR__.'/OwnershipAdoption');
}
