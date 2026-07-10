<?php

namespace Rushing\BlockSchema;

use Rushing\BlockSchema\Console\ExportManifestCommand;
use Rushing\BlockSchema\Contracts\ManifestNodeSource;
use Rushing\BlockSchema\Contracts\Schema;
use Rushing\BlockSchema\Schema\ManifestBuilder;
use Rushing\BlockSchema\Schema\NodeSchema;
use Rushing\BlockSchema\Transforms\DedupeImagesTransform;
use Rushing\BlockSchema\Transforms\OneLeadVisualTransform;
use Rushing\BlockSchema\Transforms\StripConclusionMediaTransform;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('laravel-block-schema')
            ->hasCommand(ExportManifestCommand::class);
    }

    public function registeringPackage(): void
    {
        // An empty node-type registry. The app registers its concrete Block types.
        $this->app->singleton(Schema::class, NodeSchema::class);

        $this->app->bind(
            DocumentHydrator::class,
            fn ($app) => new DocumentHydrator($app->make(Schema::class)),
        );

        $this->app->singleton(TransformPipeline::class);

        // The manifest builder resolves against the host-bound ManifestNodeSource
        // (splicewire-editor issue 06); resolving it without one is a host error.
        $this->app->bind(
            ManifestBuilder::class,
            fn ($app) => new ManifestBuilder($app->make(ManifestNodeSource::class)),
        );
    }

    public function bootingPackage(): void
    {
        $pipeline = $this->app->make(TransformPipeline::class);
        $pipeline->register(OneLeadVisualTransform::class);
        $pipeline->register(DedupeImagesTransform::class);
        $pipeline->register(StripConclusionMediaTransform::class);
    }
}
