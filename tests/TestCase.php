<?php

namespace Rushing\BlockSchema\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Rushing\BlockSchema\ServiceProvider;
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
