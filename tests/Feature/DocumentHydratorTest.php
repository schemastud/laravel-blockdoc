<?php

use Schemastud\Blockdoc\Contracts\Document;
use Schemastud\Blockdoc\DocumentHydrator;
use Schemastud\Blockdoc\Nodes\GenericNode;
use Schemastud\Blockdoc\Schema\NodeSchema;

beforeEach(function () {
    $this->schema = new NodeSchema;
    $this->schema->registerClass(SectionBlockFixture::class);
    $this->hydrator = new DocumentHydrator($this->schema);
});

it('hydrates a doc array into a typed Document of Blocks', function () {
    $doc = [
        'type' => 'doc',
        'content' => [
            ['type' => 'section', 'attrs' => ['id' => 'abc', 'heading' => 'Locations']],
        ],
    ];

    $document = $this->hydrator->hydrate($doc);

    expect($document)->toBeInstanceOf(Document::class);

    $first = $document->content()[0];
    expect($first)->toBeInstanceOf(SectionBlockFixture::class)
        ->and($first->heading)->toBe('Locations')
        ->and($first->id())->toBe('abc');
});

it('hydrates child nodes recursively', function () {
    $doc = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'section',
                'attrs' => ['id' => 'parent', 'heading' => 'Parent'],
                'content' => [
                    ['type' => 'section', 'attrs' => ['id' => 'child', 'heading' => 'Child']],
                ],
            ],
        ],
    ];

    $document = $this->hydrator->hydrate($doc);

    $parent = $document->content()[0];
    expect($parent->content())->toHaveCount(1)
        ->and($parent->content()[0]->heading)->toBe('Child')
        ->and($parent->content()[0]->id())->toBe('child');
});

it('round-trips a Block through serialize and hydrate preserving id', function () {
    $block = SectionBlockFixture::from(['heading' => 'Locations']);
    $originalId = $block->id();

    $doc = [
        'type' => 'doc',
        'content' => [$block->toArray()],
    ];

    $rehydrated = $this->hydrator->hydrate($doc)->content()[0];

    expect($rehydrated->id())->toBe($originalId)
        ->and($rehydrated->heading)->toBe('Locations');
});

it('passes unregistered prose nodes through as GenericNode', function () {
    $doc = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'Filmed in '],
                    ['type' => 'text', 'text' => 'Scotland', 'marks' => [['type' => 'strong']]],
                ],
            ],
        ],
    ];

    $document = $this->hydrator->hydrate($doc);
    $paragraph = $document->content()[0];

    expect($paragraph)->toBeInstanceOf(GenericNode::class)
        ->and($paragraph->type())->toBe('paragraph')
        ->and($paragraph->content()[1]->text())->toBe('Scotland');

    // Round-trips the raw prose structure losslessly.
    expect($document->toArray())->toBe($doc);
});

it('hydrates a typed Block whose content is prose GenericNodes', function () {
    $doc = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'section',
                'attrs' => ['id' => 's1', 'heading' => 'Locations'],
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Body.']]],
                ],
            ],
        ],
    ];

    $section = $this->hydrator->hydrate($doc)->content()[0];

    expect($section)->toBeInstanceOf(SectionBlockFixture::class)
        ->and($section->content()[0])->toBeInstanceOf(GenericNode::class)
        ->and($section->content()[0]->type())->toBe('paragraph');
});

it('still throws from Schema::resolve for an unregistered type', function () {
    $this->schema->resolve('unknown');
})->throws(InvalidArgumentException::class);
