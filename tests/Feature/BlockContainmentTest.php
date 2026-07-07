<?php

use Rushing\BlockSchema\Attributes\NodeType;
use Rushing\BlockSchema\Blocks\Block;
use Rushing\BlockSchema\Exceptions\BlockContainmentException;

/**
 * Block containment expressed as category admission (replacing the retired, inert
 * `#[NodeType]` content expression): a container admits child blocks *by category*,
 * and an illegal child is rejected on write through the one uniform admission rule
 * — not a per-block branch.
 */
#[NodeType('paragraph', 'A paragraph')]
class ParagraphFixture extends Block
{
    public function category(): ?string
    {
        return 'cat:paragraph';
    }
}

#[NodeType('image', 'An image')]
class ImageFixture extends Block
{
    public function category(): ?string
    {
        return 'cat:image';
    }
}

#[NodeType('section', 'A section admitting paragraphs and images')]
class AdmittingSectionFixture extends Block
{
    public function admitsChildCategories(): ?array
    {
        return ['cat:paragraph', 'cat:image'];
    }
}

it('admits a child whose category is in the admitted set', function () {
    $section = (new AdmittingSectionFixture)->withContent([new ParagraphFixture, new ImageFixture]);

    expect($section->content())->toHaveCount(2);
});

it('rejects a child whose category is not admitted', function () {
    $aside = new class extends Block
    {
        public function category(): ?string
        {
            return 'cat:aside';
        }
    };

    expect(fn () => (new AdmittingSectionFixture)->withContent([$aside]))
        ->toThrow(BlockContainmentException::class);
});

it('admits anything when the container is unconstrained (default)', function () {
    $paragraph = (new ParagraphFixture)->withContent([new ImageFixture]);

    expect($paragraph->content())->toHaveCount(1);
});

it('no longer accepts a content expression on #[NodeType]', function () {
    $params = (new ReflectionClass(NodeType::class))->getConstructor()->getParameters();
    $names = array_map(fn ($p) => $p->getName(), $params);

    expect($names)->not->toContain('content')
        ->and($names)->toContain('name', 'description', 'group');
});
