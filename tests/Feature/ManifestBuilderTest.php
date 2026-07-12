<?php

use Schemastud\BlockSchema\Attributes\NodeAttr;
use Schemastud\BlockSchema\Attributes\NodeType;
use Schemastud\BlockSchema\Blocks\Block;
use Schemastud\BlockSchema\Contracts\ManifestNodeSource;
use Schemastud\BlockSchema\Schema\ManifestBuilder;

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

    // A plain category slugs to itself, so no categoryId is repeated.
    expect($container)->not->toHaveKey('categoryId');
});

it('emits PM-safe category slugs for URL category $ids and preserves the raw $id as categoryId', function () {
    $source = new class extends ManifestSourceFixture
    {
        public function nodeClassesFor(string $profile): array
        {
            return [UrlCategoryBlockFixture::class];
        }

        public function docAdmitsFor(string $profile): ?array
        {
            return ['https://example.test/json-schemas/block-category/content-section'];
        }
    };

    $manifest = (new ManifestBuilder($source))->build('fixture');
    $node = $manifest['nodes'][0];

    // Last URL path segment, '-' → '_': PM content expressions only allow word chars.
    expect($node['category'])->toBe('content_section')
        ->and($node['categoryId'])->toBe('https://example.test/json-schemas/block-category/content-section')
        ->and($node['admitsChildCategories'])->toBe(['nested_thing'])
        ->and($manifest['doc']['admitsChildCategories'])->toBe(['content_section']);
});

#[NodeType('url_category_node', 'A node with URL category ids')]
class UrlCategoryBlockFixture extends Block
{
    public function __construct(public string $x = '') {}

    public function category(): ?string
    {
        return 'https://example.test/json-schemas/block-category/content-section';
    }

    public function admitsChildCategories(): ?array
    {
        return ['https://example.test/json-schemas/block-category/nested-thing'];
    }
}

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
            'paragraph', 'heading', 'blockquote', 'bullet_list', 'ordered_list',
            'list_item', 'code_block', 'horizontal_rule', 'hard_break',
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
        ->and($byName['bullet_list']['admitsChildCategories'])->toBe(['list_item'])
        ->and($byName['list_item']['category'])->toBe('list_item')
        ->and($byName['code_block']['contentExpression'])->toBe('text*')
        ->and($byName['horizontal_rule']['admitsChildCategories'])->toBe([])
        ->and($byName['hard_break']['group'])->toBe('inline')
        ->and($byName['hard_break']['category'])->toBeNull();

    // The annotation mark allows overlapping same-type marks; link carries href.
    $marks = array_column($base['marks'], null, 'name');
    expect($marks['annotation']['excludes'])->toBe('')
        ->and($marks['annotation']['attrsSchema']['properties']['id'])->toBe(['type' => ['string', 'null']])
        ->and($marks['link']['attrsSchema']['properties']['href'])->toBe(['type' => 'string']);
});
