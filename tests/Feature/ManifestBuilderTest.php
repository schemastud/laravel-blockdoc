<?php

use Rushing\BlockSchema\Attributes\NodeAttr;
use Rushing\BlockSchema\Attributes\NodeType;
use Rushing\BlockSchema\Blocks\Block;
use Rushing\BlockSchema\Contracts\ManifestNodeSource;
use Rushing\BlockSchema\Schema\ManifestBuilder;

#[NodeType('manifest_section', 'A manifest section fixture')]
class ManifestSectionBlockFixture extends Block
{
    public function __construct(
        #[NodeAttr(description: 'Section heading')]
        public string $heading = '',
    ) {}

    public function category(): ?string
    {
        return 'section';
    }
}

#[NodeType('manifest_container', 'A container fixture', group: 'block')]
class ManifestContainerBlockFixture extends Block
{
    public function __construct(
        public string $title = '',
    ) {}

    public function category(): ?string
    {
        return 'container';
    }

    public function admitsChildCategories(): ?array
    {
        return ['section'];
    }
}

class ManifestSourceFixture implements ManifestNodeSource
{
    public function profiles(): array
    {
        return ['fixture'];
    }

    public function nodeClassesFor(string $profile): array
    {
        return [ManifestContainerBlockFixture::class, ManifestSectionBlockFixture::class];
    }

    public function docAdmitsFor(string $profile): ?array
    {
        return ['section'];
    }

    public function marksFor(string $profile): array
    {
        return [];
    }
}

beforeEach(function () {
    $this->builder = new ManifestBuilder(new ManifestSourceFixture);
});

it('emits the manifest envelope: profile, version, doc admission, nodes, marks', function () {
    $manifest = $this->builder->build('fixture');

    expect($manifest['profile'])->toBe('fixture')
        ->and($manifest['version'])->toBe(1)
        ->and($manifest['doc'])->toBe(['admitsChildCategories' => ['section']])
        ->and($manifest['nodes'])->toHaveCount(2)
        ->and($manifest['marks'])->toBe([]);
});

it('emits name, description, and group from the #[NodeType] attribute', function () {
    $node = $this->builder->build('fixture')['nodes'][1];

    expect($node['name'])->toBe('manifest_section')
        ->and($node['description'])->toBe('A manifest section fixture')
        ->and($node['group'])->toBe('block');
});

it('emits category and admitsChildCategories from the Block', function () {
    [$container, $section] = $this->builder->build('fixture')['nodes'];

    expect($container['category'])->toBe('container')
        ->and($container['admitsChildCategories'])->toBe(['section'])
        ->and($section['category'])->toBe('section')
        ->and($section['admitsChildCategories'])->toBeNull();
});

it('emits admitsText false and contentExpression null for typed Blocks', function () {
    foreach ($this->builder->build('fixture')['nodes'] as $node) {
        expect($node['admitsText'])->toBeFalse()
            ->and($node['contentExpression'])->toBeNull();
    }
});

it('projects the attrs schema with id injected as a nullable string first', function () {
    $node = $this->builder->build('fixture')['nodes'][1];

    expect($node['attrsSchema']['type'])->toBe('object')
        ->and(array_key_first($node['attrsSchema']['properties']))->toBe('id')
        ->and($node['attrsSchema']['properties']['id'])->toBe(['type' => ['string', 'null']])
        ->and($node['attrsSchema']['properties']['heading']['type'])->toBe('string')
        ->and($node['attrsSchema']['properties']['heading']['description'])->toBe('Section heading');
});

it('throws for a node class without a #[NodeType] attribute', function () {
    $source = new class extends ManifestSourceFixture
    {
        public function nodeClassesFor(string $profile): array
        {
            return [BlocklessManifestFixture::class];
        }
    };

    (new ManifestBuilder($source))->build('fixture');
})->throws(LogicException::class);

class BlocklessManifestFixture extends Block
{
    public function __construct(public string $x = '') {}
}

it('ships a canonical base manifest with the base prose vocabulary and marks', function () {
    $base = ManifestBuilder::baseManifest();

    expect($base['profile'])->toBe('base')
        ->and($base['version'])->toBe(1)
        ->and(array_column($base['nodes'], 'name'))->toBe([
            'paragraph', 'heading', 'blockquote', 'bulletList', 'orderedList',
            'listItem', 'codeBlock', 'horizontalRule', 'hardBreak',
        ])
        ->and(array_column($base['marks'], 'name'))->toBe(['strong', 'em', 'code', 'link', 'annotation']);

    // Every base node carries an attrsSchema with a nullable-string id.
    foreach ($base['nodes'] as $node) {
        expect($node['attrsSchema']['properties']['id'])->toBe(['type' => ['string', 'null']], $node['name']);
    }

    // The frozen-format spot checks: textblocks, containment, escape hatches.
    $byName = array_column($base['nodes'], null, 'name');
    expect($byName['paragraph']['admitsText'])->toBeTrue()
        ->and($byName['heading']['attrsSchema']['properties']['level']['type'])->toBe('integer')
        ->and($byName['blockquote']['admitsChildCategories'])->toBe(['prose'])
        ->and($byName['bulletList']['admitsChildCategories'])->toBe(['listItem'])
        ->and($byName['listItem']['category'])->toBe('listItem')
        ->and($byName['codeBlock']['contentExpression'])->toBe('text*')
        ->and($byName['horizontalRule']['admitsChildCategories'])->toBe([])
        ->and($byName['hardBreak']['group'])->toBe('inline')
        ->and($byName['hardBreak']['category'])->toBeNull();

    // The annotation mark allows overlapping same-type marks; link carries href.
    $marks = array_column($base['marks'], null, 'name');
    expect($marks['annotation']['excludes'])->toBe('')
        ->and($marks['annotation']['attrsSchema']['properties']['id'])->toBe(['type' => ['string', 'null']])
        ->and($marks['link']['attrsSchema']['properties']['href'])->toBe(['type' => 'string']);
});
