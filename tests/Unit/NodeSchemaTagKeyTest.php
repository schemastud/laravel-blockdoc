<?php

use Schemastud\Blockdoc\Attributes\NodeType;
use Schemastud\Blockdoc\Blocks\Block;
use Schemastud\Blockdoc\Schema\NodeSchema;

/*
 * Node types spelled as JSX tags (the composition-slot rename made them PascalCase: `ContentOutline`) are not legal popcorn
 * registry keys, which are lowercase dot-separated and never folded. NodeSchema keys them by a reserved, reversible
 * one-segment encoding and keeps the tag as the public name, so registering, resolving and listing all speak the tag.
 */
#[NodeType('ContentOutline')]
class TagKeyOutlineFixture extends Block {}

#[NodeType('HTMLBlock')]
class TagKeyHtmlFixture extends Block {}

#[NodeType('faq_item')]
class TagKeySnakeFixture extends Block {}

it('registers, resolves and lists a node type spelled as a JSX tag', function () {
    $schema = new NodeSchema;
    $schema->registerClass(TagKeyOutlineFixture::class);
    $schema->registerClass(TagKeyHtmlFixture::class);
    $schema->registerClass(TagKeySnakeFixture::class);

    expect($schema->has('ContentOutline'))->toBeTrue()
        ->and($schema->resolve('ContentOutline'))->toBe(TagKeyOutlineFixture::class)
        ->and($schema->tryResolve('HTMLBlock'))->toBe(TagKeyHtmlFixture::class)
        ->and($schema->resolve('faq_item'))->toBe(TagKeySnakeFixture::class)
        ->and($schema->tryResolve('MissingTag'))->toBeNull()
        ->and($schema->types())->toEqualCanonicalizing(['ContentOutline', 'HTMLBlock', 'faq_item']);
});

it('encodes a tag reversibly and leaves a legal key alone', function (string $type) {
    expect(NodeSchema::typeFor(NodeSchema::keyFor($type)))->toBe($type);
})->with(['ContentOutline', 'HTMLBlock', 'Card2', 'Card2X', 'faq_item', 'paragraph']);

it('refuses a raw node type that spells the reserved tag prefix', function () {
    NodeSchema::keyFor('tag:content');
})->throws(InvalidArgumentException::class);
