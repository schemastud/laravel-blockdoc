<?php

use Rushing\BlockSchema\Attributes\NodeAttr;
use Rushing\BlockSchema\Attributes\NodeType;
use Rushing\BlockSchema\Blocks\Block;

#[NodeType('section', 'A body section')]
class SectionBlockFixture extends Block
{
    public function __construct(
        #[NodeAttr(description: 'Section heading')]
        public string $heading,
    ) {}
}

it('reports its node type from the #[NodeType] attribute', function () {
    $block = SectionBlockFixture::from(['heading' => 'Locations']);

    expect($block->type())->toBe('section');
});

it('stamps a fresh block with a UUIDv7 id', function () {
    $block = SectionBlockFixture::from(['heading' => 'Locations']);

    $id = $block->id();

    expect($id)->toBeString()
        ->and($id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-/i')
        ->and($block->id())->toBe($id); // stable on repeated calls
});

it('carries the id inside attrs alongside the data payload', function () {
    $block = SectionBlockFixture::from(['heading' => 'Locations']);

    $attrs = $block->attrs();

    expect($attrs)->toHaveKey('id')
        ->and($attrs['id'])->toBe($block->id())
        ->and($attrs['heading'])->toBe('Locations');
});

it('serializes to a ProseMirror node array', function () {
    $block = SectionBlockFixture::from(['heading' => 'Locations']);

    $array = $block->toArray();

    expect($array['type'])->toBe('section')
        ->and($array['attrs']['heading'])->toBe('Locations')
        ->and($array['attrs']['id'])->toBe($block->id())
        ->and($array)->not->toHaveKey('content'); // no children -> no content key
});

it('serializes child nodes under content', function () {
    $parent = SectionBlockFixture::from(['heading' => 'Parent'])
        ->withContent([SectionBlockFixture::from(['heading' => 'Child'])]);

    $array = $parent->toArray();

    expect($array['content'])->toHaveCount(1)
        ->and($array['content'][0]['attrs']['heading'])->toBe('Child');
});
