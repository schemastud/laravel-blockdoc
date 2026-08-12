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
use Splicewire\Beam\Manifest\ManifestArity;
use Splicewire\Beam\Manifest\ManifestDescriptor;
use Splicewire\Beam\Manifest\ManifestIndex;
use Splicewire\Beam\Manifest\ManifestSeam;

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

        $this->describeManifests();
    }

    /**
     * Self-describe blockdoc's two registry singletons into beam-core's `ManifestIndex` — the index of
     * indexes `splicewire:beam:manifests --json` renders, which is the entry point an agent is told to use
     * for "where do I register this" (particle-doctrine-convergence, ticket 13).
     *
     * blockdoc does not depend on `splicewire/laravel-beam` and must not: it is an MIT ProseMirror document
     * model that a non-beam Laravel app is expected to compose. So both calls are guarded on the index class
     * existing AND being bound, the same soft guard `splicewire/laravel-beam-workflows` uses for a beam-core
     * that predates a manifest. Outside a beam host — including this package's own test suite — the branch is
     * never taken and blockdoc simply is not listed. The precedent for a schemastud package describing itself
     * into a splicewire index already exists (`schemastud/laravel-data-schema-org` describes
     * `SchemaOrgRegistry`); the dependency stays one-way and optional.
     */
    protected function describeManifests(): void
    {
        if (! class_exists(ManifestIndex::class) || ! $this->app->bound(ManifestIndex::class)) {
            return;
        }

        $index = $this->app->make(ManifestIndex::class);
        $pkg = 'schemastud/laravel-blockdoc';

        $index->describe(new ManifestDescriptor(
            name: 'Schema (NodeSchema)',
            of: 'ProseMirror node types — nodeType slug => Block class, read by the DocumentHydrator',
            // SingletonAccumulator, NOT AttributeScan, even though `registerClass()` reads `#[NodeType]` off
            // the class it is handed. AttributeScan means the registry DISCOVERS its entries by walking the
            // filesystem and caching a manifest; nothing here scans. The host chooses which Block classes
            // exist and hands each one over from its provider — the attribute only supplies the slug, so the
            // injection point is the singleton, and a host that never calls register() has an empty schema.
            seam: ManifestSeam::SingletonAccumulator,
            // PickOne: hydration resolves ONE Block class per node type. Note the deviation worth knowing —
            // a miss THROWS rather than falling back to a generic default node, because a document
            // containing a type the app never registered is corrupt, not degraded.
            arity: ManifestArity::PickOne,
            registerHint: 'resolve the Schema contract and call registerClass(YourBlock::class) (slug from its #[NodeType]) or register($type, $class) from your provider',
            where: Schema::class,
            package: $pkg, order: 50,
        ));

        $index->describe(new ManifestDescriptor(
            name: 'TransformPipeline',
            of: 'document transforms applied to a ProseMirror doc in registration order (structural first, then host/grounding-driven)',
            // The seam/arity split this pair of enums exists for. The SEAM is SingletonAccumulator — you
            // resolve the shared pipeline and `register()` a transform class-string from your provider —
            // even though the ARITY is ComposeMany. PipelineChain would be wrong: that shape means the stage
            // list is passed to a constructor (as `ParticleWriter`'s is), and here there is no constructor
            // seam at all, so a host told "pass an ordered $stages list" would find nothing to pass it to.
            seam: ManifestSeam::SingletonAccumulator,
            // ComposeMany, not RunAll: `transform()` threads the document through every registered transform
            // in turn, each one receiving the previous one's output. Order is not merely significant, it is
            // the semantics — ADR-0021 puts the package's structural transforms before the app's
            // grounding-driven ones for exactly that reason.
            arity: ManifestArity::ComposeMany,
            registerHint: 'resolve the singleton and register(YourTransform::class) from your provider — it appends AFTER the structural transforms',
            where: TransformPipeline::class,
            package: $pkg, order: 51,
        ));
    }
}
