<?php

namespace Schemastud\Blockdoc\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Schemastud\Blockdoc\ServiceProvider;
use Spatie\LaravelData\LaravelDataServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelDataServiceProvider::class,
            ServiceProvider::class,
        ];
    }
}
