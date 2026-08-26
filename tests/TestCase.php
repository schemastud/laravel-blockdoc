<?php

namespace Schemastud\Blockdoc\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\Popcorn\Laravel\PopcornServiceProvider;
use Schemastud\Blockdoc\ServiceProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     *
     * PopcornServiceProvider is NOT optional here and testbench does not auto-discover it. Without it
     * `RegistryIndex` is auto-resolvable but unshared, so every `describe()` lands on a throwaway and
     * the suite stays green over an empty index (registry-kernel ticket 27 D3). RegistryIndexTest's
     * tripwire is what keeps this honest.
     */
    protected function getPackageProviders($app): array
    {
        return [
            PopcornServiceProvider::class,
            LaravelDataServiceProvider::class,
            ServiceProvider::class,
        ];
    }
}
