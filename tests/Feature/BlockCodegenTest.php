<?php

use Schemastud\BlockSchema\Attributes\NodeAttr;
use Schemastud\BlockSchema\Attributes\NodeType;
use Schemastud\BlockSchema\Blocks\Block;
use Schemastud\BlockSchema\Codegen\BlockClassGenerator;
use Schemastud\BlockSchema\Codegen\BlockCodegenCache;
use Schemastud\BlockSchema\DocumentHydrator;
use Schemastud\BlockSchema\Schema\NodeSchema;

// ---------------------------------------------------------------------------
// Hand-authored equivalent — the parity baseline
// ---------------------------------------------------------------------------

#[NodeType('codegen_card', 'A custom card embed (hand-authored parity fixture)', group: 'embed')]
class CodegenCardFixture extends Block
{
    public function __construct(
        #[NodeAttr(description: 'Card image source URL', required: true)]
        public string $src,
        #[NodeAttr(description: 'Card title', required: true)]
        public string $title,
        #[NodeAttr(description: 'Optional caption text', required: false, default: null)]
        public ?string $caption = null,
    ) {}
}

// ---------------------------------------------------------------------------
// Fixture JSON Schema (what the codegen compiles FROM)
// ---------------------------------------------------------------------------

function codegenFixtureSchema(): array
{
    return [
        'type' => 'object',
        'description' => 'A custom card embed (hand-authored parity fixture)',
        'properties' => [
            'src' => [
                'type' => 'string',
                'description' => 'Card image source URL',
            ],
            'title' => [
                'type' => 'string',
                'description' => 'Card title',
            ],
            'caption' => [
                'type' => ['string', 'null'],
                'description' => 'Optional caption text',
            ],
        ],
        'required' => ['src', 'title'],
    ];
}

// ---------------------------------------------------------------------------
// BlockClassGenerator
// ---------------------------------------------------------------------------

it('generates valid PHP class code from a fixture JSON Schema', function () {
    $generator = new BlockClassGenerator;

    $code = $generator->generate(
        className: 'GeneratedCodegenCard',
        nodeType: 'codegen_card',
        schema: codegenFixtureSchema(),
    );

    expect($code)
        ->toContain('class GeneratedCodegenCard extends Block')
        ->toContain("#[NodeType('codegen_card'")
        ->toContain('public string $src')
        ->toContain('public string $title')
        ->toContain('public ?string $caption');
});

it('marks required properties as required and optional as not required', function () {
    $generator = new BlockClassGenerator;

    $code = $generator->generate(
        className: 'GeneratedCodegenCard',
        nodeType: 'codegen_card',
        schema: codegenFixtureSchema(),
    );

    // src and title are required → no default value
    expect($code)->toContain('public string $src')
        ->toContain('public string $title');

    // caption is optional → nullable with null default
    expect($code)->toContain('public ?string $caption = null');
});

// ---------------------------------------------------------------------------
// BlockCodegenCache — write to cache, load back
// ---------------------------------------------------------------------------

it('compiles generated code to a cache file and loads the class', function () {
    $generator = new BlockClassGenerator;
    $cache = new BlockCodegenCache(sys_get_temp_dir());

    $code = $generator->generate(
        className: 'GeneratedCodegenCard',
        nodeType: 'codegen_card',
        schema: codegenFixtureSchema(),
    );

    $fqcn = $cache->compile('GeneratedCodegenCard', 'codegen_card', $code);

    expect(class_exists($fqcn))->toBeTrue()
        ->and($fqcn)->toBe('GeneratedCodegenCard');
});

// ---------------------------------------------------------------------------
// Parity — generated class hydrates/renders identically to hand-authored
// ---------------------------------------------------------------------------

it('generated Block hydrates from attrs identically to the hand-authored equivalent', function () {
    $generator = new BlockClassGenerator;
    $cache = new BlockCodegenCache(sys_get_temp_dir());

    $code = $generator->generate(
        className: 'GeneratedCodegenCard',
        nodeType: 'codegen_card',
        schema: codegenFixtureSchema(),
    );
    $fqcn = $cache->compile('GeneratedCodegenCard', 'codegen_card', $code);

    $attrs = ['src' => 'hero.jpg', 'title' => 'My Card', 'caption' => 'Some caption'];

    /** @var Block $generated */
    $generated = $fqcn::from($attrs);
    $handAuthored = CodegenCardFixture::from($attrs);

    // Both report the same node type
    expect($generated->type())->toBe($handAuthored->type());

    // attrs() fields match (excluding generated id)
    $generatedAttrs = $generated->attrs();
    $expectedAttrs = $handAuthored->attrs();
    unset($generatedAttrs['id'], $expectedAttrs['id']);
    expect($generatedAttrs)->toBe($expectedAttrs);
});

it('generated Block works through DocumentHydrator end-to-end', function () {
    $generator = new BlockClassGenerator;
    $cache = new BlockCodegenCache(sys_get_temp_dir());

    $code = $generator->generate(
        className: 'GeneratedCodegenCard',
        nodeType: 'codegen_card',
        schema: codegenFixtureSchema(),
    );
    $fqcn = $cache->compile('GeneratedCodegenCard', 'codegen_card', $code);

    $schema = new NodeSchema;
    $schema->register('codegen_card', $fqcn);
    $hydrator = new DocumentHydrator($schema);

    $doc = $hydrator->hydrate([
        'type' => 'doc',
        'content' => [[
            'type' => 'codegen_card',
            'attrs' => ['src' => 'test.jpg', 'title' => 'Hello', 'caption' => null],
        ]],
    ]);

    $nodes = $doc->content();
    expect($nodes)->toHaveCount(1)
        ->and($nodes[0]->type())->toBe('codegen_card')
        ->and($nodes[0]->attrs()['src'])->toBe('test.jpg');
});
