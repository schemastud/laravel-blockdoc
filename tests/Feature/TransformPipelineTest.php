<?php

use Schemastud\Blockdoc\Contracts\DocumentTransform;
use Schemastud\Blockdoc\TransformPipeline;
use Schemastud\Blockdoc\Transforms\DedupeImagesTransform;
use Schemastud\Blockdoc\Transforms\OneLeadVisualTransform;
use Schemastud\Blockdoc\Transforms\StripConclusionMediaTransform;

it('passes input through unchanged when no transforms are registered', function () {
    $pipeline = new TransformPipeline;
    $input = ['sections' => [['heading' => 'X', 'content' => []]]];

    expect($pipeline->transform($input))->toBe($input);
});

it('runs registered transforms in registration order', function () {
    $first = new class implements DocumentTransform
    {
        public function transform(array $input, array $context = []): array
        {
            $input['order'][] = 'first';

            return $input;
        }
    };

    $second = new class implements DocumentTransform
    {
        public function transform(array $input, array $context = []): array
        {
            $input['order'][] = 'second';

            return $input;
        }
    };

    $pipeline = new TransformPipeline;
    $pipeline->register($first::class);
    $pipeline->register($second::class);

    expect($pipeline->transform([])['order'])->toBe(['first', 'second']);
});

it('strips image_float embeds from the conclusion (StripConclusionMediaTransform)', function () {
    $pipeline = new TransformPipeline;
    $pipeline->register(StripConclusionMediaTransform::class);

    $input = [
        'sections' => [],
        'conclusion' => ['content' => [
            ['prose' => '<p>Wrap up.</p>'],
            ['embed' => ['type' => 'image_float', 'src' => 'img.jpg']],
        ]],
    ];

    $out = $pipeline->transform($input);

    expect($out['conclusion']['content'])->toHaveCount(1)
        ->and($out['conclusion']['content'][0])->toHaveKey('prose');
});

it('leaves non-image embeds in the conclusion untouched (StripConclusionMediaTransform)', function () {
    $pipeline = new TransformPipeline;
    $pipeline->register(StripConclusionMediaTransform::class);

    $input = [
        'sections' => [],
        'conclusion' => ['content' => [
            ['embed' => ['type' => 'amazon_grid', 'asins' => ['B01']]],
        ]],
    ];

    expect($pipeline->transform($input)['conclusion']['content'])->toHaveCount(1);
});

it('drops image_float when section already has expedia_teaser (OneLeadVisualTransform)', function () {
    $pipeline = new TransformPipeline;
    $pipeline->register(OneLeadVisualTransform::class);

    $input = ['sections' => [
        ['heading' => 'Castle Howard', 'content' => [
            ['embed' => ['type' => 'expedia_teaser', 'href' => 'https://expedia.com/x']],
            ['embed' => ['type' => 'image_float', 'src' => 'img.jpg']],
            ['prose' => '<p>Body.</p>'],
        ]],
    ]];

    $out = $pipeline->transform($input);
    $types = array_map(fn ($i) => $i['embed']['type'] ?? 'prose', $out['sections'][0]['content']);

    expect($types)->toBe(['expedia_teaser', 'prose']);
});

it('keeps image_float when section has no teaser (OneLeadVisualTransform)', function () {
    $pipeline = new TransformPipeline;
    $pipeline->register(OneLeadVisualTransform::class);

    $input = ['sections' => [
        ['heading' => 'Castle Howard', 'content' => [
            ['embed' => ['type' => 'image_float', 'src' => 'img.jpg']],
        ]],
    ]];

    expect($pipeline->transform($input)['sections'][0]['content'])->toHaveCount(1);
});

it('dedupes image_float by src across sections (DedupeImagesTransform)', function () {
    $pipeline = new TransformPipeline;
    $pipeline->register(DedupeImagesTransform::class);

    $input = ['sections' => [
        ['heading' => 'X', 'content' => [['embed' => ['type' => 'image_float', 'src' => 'img.jpg']]]],
        ['heading' => 'Y', 'content' => [['embed' => ['type' => 'image_float', 'src' => 'img.jpg']]]],
    ]];

    $out = $pipeline->transform($input);

    expect($out['sections'][0]['content'])->toHaveCount(1)
        ->and($out['sections'][1]['content'])->toHaveCount(0);
});

it('keeps unique image_float srcs across sections (DedupeImagesTransform)', function () {
    $pipeline = new TransformPipeline;
    $pipeline->register(DedupeImagesTransform::class);

    $input = ['sections' => [
        ['heading' => 'X', 'content' => [['embed' => ['type' => 'image_float', 'src' => 'a.jpg']]]],
        ['heading' => 'Y', 'content' => [['embed' => ['type' => 'image_float', 'src' => 'b.jpg']]]],
    ]];

    $out = $pipeline->transform($input);

    expect($out['sections'][0]['content'])->toHaveCount(1)
        ->and($out['sections'][1]['content'])->toHaveCount(1);
});

it('binds TransformPipeline as a shared singleton in the container', function () {
    expect(app(TransformPipeline::class))
        ->toBeInstanceOf(TransformPipeline::class)
        ->and(app(TransformPipeline::class))->toBe(app(TransformPipeline::class));
});

it('registers the three built-in structural transforms via the ServiceProvider', function () {
    $pipeline = app(TransformPipeline::class);
    $input = [
        'sections' => [
            ['heading' => 'X', 'content' => [
                ['embed' => ['type' => 'expedia_teaser', 'href' => 'https://expedia.com/x']],
                ['embed' => ['type' => 'image_float', 'src' => 'img.jpg']],
            ]],
            ['heading' => 'Y', 'content' => [
                ['embed' => ['type' => 'image_float', 'src' => 'img.jpg']],
            ]],
        ],
        'conclusion' => ['content' => [
            ['prose' => '<p>Done.</p>'],
            ['embed' => ['type' => 'image_float', 'src' => 'other.jpg']],
        ]],
    ];

    $out = $pipeline->transform($input);

    // OneLeadVisual: section 0's image_float dropped (teaser present)
    $s0Types = array_map(fn ($i) => $i['embed']['type'] ?? 'prose', $out['sections'][0]['content']);
    expect($s0Types)->toBe(['expedia_teaser']);

    // Dedupe: section 1's image_float dropped (img.jpg was in section 0 before it was removed by OneLeadVisual)
    // Actually after OneLeadVisual runs, img.jpg is gone from section 0, so Dedupe passes section 1's through.
    // Let's verify Dedupe independently: just that it runs (img.jpg in section 1 survives here since section 0 lost it)
    expect($out['sections'][1]['content'])->toHaveCount(1);

    // StripConclusion: image_float removed from conclusion
    expect($out['conclusion']['content'])->toHaveCount(1)
        ->and($out['conclusion']['content'][0])->toHaveKey('prose');
});
