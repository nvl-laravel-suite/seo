<?php

declare(strict_types=1);

use Nvl\Seo\Tests\LegacyOwnershipTestCase;
use Nvl\Seo\Tests\OwnershipTestCase;
use Nvl\Seo\Tests\TenancyTestCase;
use Nvl\Seo\Tests\TestCase;

uses(TestCase::class)->in(__DIR__.'/Feature');
uses(TenancyTestCase::class)->in(__DIR__.'/Tenancy');

pest()->extend(OwnershipTestCase::class)->in(__DIR__.'/Ownership');

pest()->extend(LegacyOwnershipTestCase::class)->in(__DIR__.'/OwnershipAdoption');
