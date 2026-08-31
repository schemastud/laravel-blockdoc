<?php

namespace Schemastud\Blockdoc;

use Rushing\Popcorn\Registries\RegistryIndex;
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
        //
        // ⚠️ Bound on the CONCRETE and forwarded from the interface, rather than the other way round.
        // `#[IsRegistry]` lives on `NodeSchema` — the class owning the keyspace — while consumers
        // type-hint `Schema`. With `singleton(Schema::class, NodeSchema::class)` alone the singleton
        // lives under `Schema::class` and `make(NodeSchema::class)` builds a FRESH one, so the declared
        // name and the bound name were two different objects. Registry-kernel 73's baked membership
        // resolves a registry by its DECLARED class, which turned that from cosmetic into a fresh, empty
        // `blockdoc.node-types` in the index.
        //
        // ⚠️ And NOT `alias(Schema::class, NodeSchema::class)`, which is the obvious one-liner and
        // **hangs the process**: the singleton's concrete IS `NodeSchema::class`, so resolving it walks
        // the alias back to `Schema::class` and recurses until the stack dies — exit 255, no output, no
        // catchable error. Measured 2026-08-31 at `~/Herd/splicewire-app`.
        $this->app->singleton(NodeSchema::class);
        $this->app->singleton(Schema::class, fn ($app) => $app->make(NodeSchema::class));

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

    public function packageBooted(): void
    {
        // Declaring and indexing are two acts (registry-kernel ticket 21 D1): the #[IsRegistry] on each
        // class says what root it owns, and this is what puts it IN the index. An owner describes DOWN
        // from its own boot, after whatever fills it; the index never reaches up.
        $index = $this->app->make(RegistryIndex::class);

        $index->describe($this->app->make(TransformPipeline::class), by: self::class);
        $index->describe($this->app->make(NodeSchema::class), by: self::class);
    }
}
