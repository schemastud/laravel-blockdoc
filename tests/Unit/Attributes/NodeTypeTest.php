<?php

use Schemastud\BlockSchema\Attributes\NodeAttr;
use Schemastud\BlockSchema\Attributes\NodeType;

it('can be read via reflection from an annotated class', function () {
    #[NodeType('section', 'A body section')]
    class TestBlock
    {
        public function __construct(
            #[NodeAttr(description: 'Section heading', example: 'The Filming Locations')]
            public string $heading,
        ) {}
    }

    $classRef = new ReflectionClass(TestBlock::class);
    $attrs = $classRef->getAttributes(NodeType::class);

    expect($attrs)->toHaveCount(1);

    $nodeType = $attrs[0]->newInstance();
    expect($nodeType->name)->toBe('section')
        ->and($nodeType->description)->toBe('A body section');
});

it('can read NodeAttr from a constructor parameter', function () {
    #[NodeType('opening')]
    class TestOpeningBlock
    {
        public function __construct(
            #[NodeAttr(description: 'Opening prose', required: true)]
            public string $prose,
        ) {}
    }

    $classRef = new ReflectionClass(TestOpeningBlock::class);
    $params = $classRef->getConstructor()->getParameters();
    $attrs = $params[0]->getAttributes(NodeAttr::class);

    expect($attrs)->toHaveCount(1);

    $prop = $attrs[0]->newInstance();
    expect($prop->description)->toBe('Opening prose')
        ->and($prop->required)->toBeTrue();
});
