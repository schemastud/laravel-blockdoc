<?php

use Schemastud\BlockSchema\Contracts\Schema;
use Schemastud\BlockSchema\DocumentHydrator;
use Schemastud\BlockSchema\Schema\NodeSchema;

it('binds the Schema as a shared singleton', function () {
    expect(app(Schema::class))
        ->toBeInstanceOf(NodeSchema::class)
        ->and(app(Schema::class))->toBe(app(Schema::class));
});

it('starts with an empty schema and registers no concrete node types', function () {
    expect(app(Schema::class)->has('section'))->toBeFalse();
});

it('resolves a DocumentHydrator backed by the container Schema', function () {
    app(Schema::class)->registerClass(SectionBlockFixture::class);

    $document = app(DocumentHydrator::class)->hydrate([
        'type' => 'doc',
        'content' => [['type' => 'section', 'attrs' => ['id' => 'x', 'heading' => 'Hi']]],
    ]);

    expect($document->content()[0])->toBeInstanceOf(SectionBlockFixture::class);
});
