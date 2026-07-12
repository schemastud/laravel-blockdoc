<?php

use Schemastud\BlockSchema\Attributes\NodeAttr;
use Schemastud\BlockSchema\Attributes\NodeType;
use Schemastud\BlockSchema\BlockDocumentBuilder;
use Schemastud\BlockSchema\Blocks\Block;
use Schemastud\BlockSchema\Contracts\Document;
use Schemastud\BlockSchema\Contracts\DocumentRenderer;
use Schemastud\BlockSchema\Contracts\Schema;

#[NodeType('opening', 'Opening block fixture')]
class OpeningBlockFixture extends Block {}

#[NodeType('section', 'Section block fixture')]
class BuilderSectionFixture extends Block
{
    public function __construct(
        #[NodeAttr(description: 'Section heading')]
        public string $heading,
    ) {}
}

#[NodeType('faq', 'FAQ block fixture')]
class FaqBlockFixture extends Block {}

#[NodeType('faq_item', 'FAQ item fixture')]
class FaqItemFixture extends Block
{
    public function __construct(
        #[NodeAttr(description: 'The question')]
        public string $question,
    ) {}
}

#[NodeType('conclusion', 'Conclusion block fixture')]
class ConclusionBlockFixture extends Block {}

beforeEach(function () {
    $this->app->bind(DocumentRenderer::class, fn () => new class implements DocumentRenderer
    {
        public function render(Document $document): string
        {
            return '<rendered/>';
        }
    });

    $schema = app(Schema::class);
    $schema->registerClass(OpeningBlockFixture::class);
    $schema->registerClass(BuilderSectionFixture::class);
    $schema->registerClass(FaqBlockFixture::class);
    $schema->registerClass(FaqItemFixture::class);
    $schema->registerClass(ConclusionBlockFixture::class);
});

it('builds an empty document when given no data', function () {
    $document = (new BlockDocumentBuilder)->build([]);

    expect($document->content())->toBeEmpty();
});

it('builds a document with all top-level block types resolved from the registry', function () {
    $document = (new BlockDocumentBuilder)->build([
        'opening' => ['prose' => '<p>Welcome.</p>'],
        'sections' => [
            ['heading' => 'Doune Castle', 'content' => [['prose' => '<p>Served as Winterfell.</p>']]],
        ],
        'faq' => [
            ['question' => 'Where was it filmed?', 'prose' => '<p>In Scotland.</p>'],
        ],
        'conclusion' => ['content' => [['prose' => '<p>Plan your trip.</p>']]],
    ]);

    $types = array_map(fn ($n) => $n->type(), $document->content());
    expect($types)->toBe(['opening', 'section', 'faq', 'conclusion']);
});

it('resolves section heading and prose content via registry', function () {
    $document = (new BlockDocumentBuilder)->build([
        'sections' => [
            ['heading' => 'Locations', 'content' => [['prose' => '<p>Body text.</p>']]],
        ],
    ]);

    $section = $document->content()[0];
    expect($section->type())->toBe('section')
        ->and($section->attrs()['heading'])->toBe('Locations')
        ->and($section->content())->toHaveCount(1)
        ->and($section->content()[0]->type())->toBe('paragraph');
});

it('resolves faq_item children via registry', function () {
    $document = (new BlockDocumentBuilder)->build([
        'faq' => [
            ['question' => 'Is it open?', 'prose' => '<p>Yes.</p>'],
        ],
    ]);

    $faq = $document->content()[0];
    expect($faq->type())->toBe('faq')
        ->and($faq->content())->toHaveCount(1)
        ->and($faq->content()[0]->type())->toBe('faq_item')
        ->and($faq->content()[0]->attrs()['question'])->toBe('Is it open?');
});

it('compiles to body_blocks array and body_blocks_html string', function () {
    $compiled = (new BlockDocumentBuilder)->compile([
        'opening' => ['prose' => '<p>Welcome.</p>'],
    ]);

    expect($compiled)->toHaveKeys(['body_blocks', 'body_blocks_html'])
        ->and($compiled['body_blocks']['type'])->toBe('doc')
        ->and($compiled['body_blocks']['content'][0]['type'])->toBe('opening')
        ->and($compiled['body_blocks_html'])->toBe('<rendered/>');
});
