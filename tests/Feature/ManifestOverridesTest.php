<?php

use Schemastud\Blockdoc\Attributes\NodeAttr;
use Schemastud\Blockdoc\Attributes\NodeType;
use Schemastud\Blockdoc\Attributes\Skin;
use Schemastud\Blockdoc\Attributes\Widget;
use Schemastud\Blockdoc\Blocks\Block;
use Schemastud\Blockdoc\Contracts\ManifestNodeSource;
use Schemastud\Blockdoc\Schema\ManifestBuilder;

// A Block that authors overrides: a node-level skin + a per-attr widget.
#[NodeType('rated_product', 'A product with a rating')]
#[Skin('product-card')]
class OverrideProductBlockFixture extends Block
{
    public function __construct(
        #[NodeAttr(description: 'Star rating')]
        #[Widget('star-rating')]
        public int $rating = 0,
        #[NodeAttr(description: 'Product title')]
        public string $title = '',
    ) {}

    public function category(): ?string
    {
        return 'product';
    }
}

// A Block that authors NO overrides — the default case must stay clean.
#[NodeType('plain_note', 'A plain note')]
class PlainNoteBlockFixture extends Block
{
    public function __construct(
        public string $body = '',
    ) {}

    public function category(): ?string
    {
        return 'note';
    }
}

class OverrideManifestSourceFixture implements ManifestNodeSource
{
    public function profiles(): array
    {
        return ['fixture'];
    }

    public function nodeClassesFor(string $profile): array
    {
        return [OverrideProductBlockFixture::class, PlainNoteBlockFixture::class];
    }

    public function docAdmitsFor(string $profile): ?array
    {
        return ['product'];
    }

    public function marksFor(string $profile): array
    {
        return [];
    }
}

beforeEach(function () {
    $this->builder = new ManifestBuilder(new OverrideManifestSourceFixture);
    $this->nodes = collect($this->builder->build('fixture')['nodes'])->keyBy('name');
});

it('emits x-skin on the node entry when #[Skin] is authored', function () {
    expect($this->nodes['rated_product']['x-skin'])->toBe('product-card');
});

it('emits x-widget per-attr inside attrsSchema when #[Widget] is authored', function () {
    $props = $this->nodes['rated_product']['attrsSchema']['properties'];

    expect($props['rating']['x-widget'])->toBe('star-rating')
        ->and($props['rating']['type'])->toBe('integer'); // the property itself survives
});

it('does not emit x-widget for a property without #[Widget] (override-only)', function () {
    $props = $this->nodes['rated_product']['attrsSchema']['properties'];

    expect($props['title'])->not->toHaveKey('x-widget');
});

it('emits neither x-skin nor x-widget for a Block with no overrides authored', function () {
    $note = $this->nodes['plain_note'];

    expect($note)->not->toHaveKey('x-skin');
    expect($note['attrsSchema']['properties']['body'] ?? [])->not->toHaveKey('x-widget');
});
