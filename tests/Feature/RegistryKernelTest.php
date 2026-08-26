<?php

use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryIndex;
use Schemastud\Blockdoc\Contracts\DocumentTransform;
use Schemastud\Blockdoc\Contracts\Schema;
use Schemastud\Blockdoc\TransformPipeline;
use Schemastud\Blockdoc\Transforms\DedupeImagesTransform;
use Schemastud\Blockdoc\Transforms\OneLeadVisualTransform;
use Schemastud\Blockdoc\Transforms\StripConclusionMediaTransform;

// The tripwire (registry-kernel ticket 27 D3). An unshared RegistryIndex is auto-resolvable, so every
// describe() below would land on a throwaway and every assertion in this file would pass over an empty
// index. This is the assertion that cannot.
it('shares one RegistryIndex across the container', function () {
    expect(app(RegistryIndex::class))->toBe(app(RegistryIndex::class));
});

it('describes both blockdoc roots into the index at boot', function () {
    $index = app(RegistryIndex::class);

    expect($index->has('blockdoc.transforms'))->toBeTrue()
        ->and($index->resolve('blockdoc.transforms'))->toBe(app(TransformPipeline::class))
        ->and($index->has('blockdoc.node-types'))->toBeTrue()
        ->and($index->resolve('blockdoc.node-types'))->toBe(app(Schema::class));
});

it('round-trips a transform through the port vocabulary and the contract alike', function () {
    $pipeline = new TransformPipeline;
    $pipeline->register(StripConclusionMediaTransform::class);

    expect($pipeline)->toBeInstanceOf(Registry::class)
        ->and($pipeline->ids())->toBe(['strip-conclusion-media-transform'])
        ->and($pipeline->transforms())->toBe([StripConclusionMediaTransform::class])
        ->and($pipeline->has('strip-conclusion-media-transform'))->toBeTrue()
        ->and($pipeline->resolve('strip-conclusion-media-transform'))->toBe(StripConclusionMediaTransform::class)
        ->and((string) $pipeline->keys()[0])->toBe('blockdoc.transforms.strip-conclusion-media-transform');
});

// ComposeMany: the chain's ORDER is the semantics, so it is asserted rather than assumed. The
// service provider's three structural transforms must come out in the order it registered them.
it('holds the provider-registered transforms in registration order', function () {
    expect(app(TransformPipeline::class)->transforms())->toBe([
        OneLeadVisualTransform::class,
        DedupeImagesTransform::class,
        StripConclusionMediaTransform::class,
    ]);
});

// The `Supersede`-moves-to-the-end trap, pinned as behaviour: this registry declares `Admit`, so a
// re-registration APPENDS a second live entry and never relocates the first. Under `Supersede` the
// first entry would move to the end of the chain, which for a pipeline is a visible defect.
it('keeps a re-registered transform in its original chain position', function () {
    $pipeline = new TransformPipeline;
    $pipeline->register(OneLeadVisualTransform::class);
    $pipeline->register(DedupeImagesTransform::class);
    $pipeline->register(OneLeadVisualTransform::class);

    expect($pipeline->transforms())->toBe([
        OneLeadVisualTransform::class,
        DedupeImagesTransform::class,
        OneLeadVisualTransform::class,
    ]);
});

it('keys an anonymous transform class without tripping the key charset', function () {
    $anonymous = new class implements DocumentTransform
    {
        public function transform(array $input, array $context = []): array
        {
            return $input;
        }
    };

    $pipeline = new TransformPipeline;
    $pipeline->register($anonymous::class);

    expect($pipeline->transforms())->toBe([$anonymous::class])
        ->and($pipeline->ids()[0])->toStartWith('anonymous-');
});

it('round-trips a node type through the Schema port and the contract alike', function () {
    $schema = app(Schema::class);
    $schema->register('section', SectionBlockFixture::class);

    expect($schema)->toBeInstanceOf(Registry::class)
        ->and($schema->has('section'))->toBeTrue()
        ->and($schema->resolve('section'))->toBe(SectionBlockFixture::class)
        ->and($schema->tryResolve('nope'))->toBeNull()
        ->and($schema->types())->toBe(['section'])
        ->and((string) $schema->keys()[0])->toBe('blockdoc.node-types.section');
});
