<?php

use Rushing\BlockSchema\Attributes\NodeAttr;
use Rushing\BlockSchema\Attributes\NodeType;
use Rushing\BlockSchema\Blocks\Block;
use Rushing\BlockSchema\Schema\BlockJsonSchemaGenerator;
use Spatie\LaravelData\Data;

#[NodeType('faq', 'A frequently-asked-question entry')]
class FaqBlockSchemaFixture extends Block
{
    public function __construct(
        #[NodeAttr(description: 'The question being answered', example: 'Where was it filmed?')]
        public string $question,
        #[NodeAttr(description: 'The answer', required: false, default: null)]
        public ?string $answer = null,
    ) {}
}

class PlainDataFixture extends Data
{
    public function __construct(public string $name) {}
}

it('only generates for Block subclasses', function () {
    $generator = new BlockJsonSchemaGenerator;

    expect($generator->canGenerate(new ReflectionClass(FaqBlockSchemaFixture::class)))->toBeTrue()
        ->and($generator->canGenerate(new ReflectionClass(PlainDataFixture::class)))->toBeFalse();
});

it('uses the #[NodeType] description as the schema description', function () {
    $schema = (new BlockJsonSchemaGenerator)->generate(new ReflectionClass(FaqBlockSchemaFixture::class));

    expect($schema['type'])->toBe('object')
        ->and($schema['description'])->toBe('A frequently-asked-question entry')
        ->and($schema['properties'])->toHaveKeys(['question', 'answer']);
});

it('bridges #[NodeAttr] description and example onto properties', function () {
    $schema = (new BlockJsonSchemaGenerator)->generate(new ReflectionClass(FaqBlockSchemaFixture::class));

    expect($schema['properties']['question']['description'])->toBe('The question being answered')
        ->and($schema['properties']['question']['examples'])->toBe(['Where was it filmed?'])
        ->and($schema['properties']['answer']['description'])->toBe('The answer');
});

it('honors #[NodeAttr] required to compute the required list', function () {
    $schema = (new BlockJsonSchemaGenerator)->generate(new ReflectionClass(FaqBlockSchemaFixture::class));

    expect($schema['required'])->toContain('question')
        ->and($schema['required'] ?? [])->not->toContain('answer');
});
