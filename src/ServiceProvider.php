<?php

namespace Schemastud\Blockdoc;

use Schemastud\Blockdoc\Console\ExportManifestCommand;
use Schemastud\Blockdoc\Contracts\ManifestNodeSource;
use Schemastud\Blockdoc\Contracts\Schema;
use Schemastud\Blockdoc\Schema\ManifestBuilder;
use Schemastud\Blockdoc\Schema\NodeSchema;
use Schemastud\Blockdoc\Transforms\DedupeImagesTransform;
use Schemastud\Blockdoc\Transforms\OneLeadVisualTransform;
use Schemastud\Blockdoc\Transforms\StripConclusionMediaTransform;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('laravel-blockdoc')
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
