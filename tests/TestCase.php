<?php

namespace Schemastud\BlockSchema\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Schemastud\BlockSchema\ServiceProvider;
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
