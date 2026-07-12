<?php

use Schemastud\BlockSchema\NodeIdRematcher;

function rematchParagraph(?string $id, string $text): array
{
    $node = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]];

    if ($id !== null) {
        $node['attrs'] = ['id' => $id];
    }

    return $node;
}

it('reattaches dropped ids to the best-matching surviving nodes', function () {
    $old = ['type' => 'doc', 'content' => [
        rematchParagraph('id-1', 'Rapid cooling keeps food out of the danger zone.'),
        rematchParagraph('id-2', 'Shallow pans and ice baths speed the process.'),
        rematchParagraph('id-3', 'Label everything with the cooling start time.'),
    ]];

    // The generation output kept two passages (one lightly edited), dropped
    // every id, and introduced one genuinely new passage.
    $new = ['type' => 'doc', 'content' => [
        rematchParagraph(null, 'Rapid cooling keeps foods out of the temperature danger zone.'),
        rematchParagraph(null, 'A wholly new closing thought about verification.'),
        rematchParagraph(null, 'Label everything with the cooling start time.'),
    ]];

    $rematched = (new NodeIdRematcher)->rematch($old, $new);

    expect($rematched['content'][0]['attrs']['id'])->toBe('id-1')
        ->and($rematched['content'][2]['attrs']['id'])->toBe('id-3')
        ->and($rematched['content'][1]['attrs']['id'] ?? null)->toBeNull();
});

it('never reassigns an id the new doc already carries, and spends each old id once', function () {
    $old = ['type' => 'doc', 'content' => [
        rematchParagraph('id-1', 'The same sentence twice.'),
    ]];

    $new = ['type' => 'doc', 'content' => [
        rematchParagraph('id-1', 'The same sentence twice.'),
        rematchParagraph(null, 'The same sentence twice.'),
    ]];

    $rematched = (new NodeIdRematcher)->rematch($old, $new);

    expect($rematched['content'][0]['attrs']['id'])->toBe('id-1')
        ->and($rematched['content'][1]['attrs']['id'] ?? null)->toBeNull();
});

it('respects node type and the similarity threshold', function () {
    $old = ['type' => 'doc', 'content' => [
        rematchParagraph('id-1', 'Entirely unrelated prose about shipping containers.'),
        ['type' => 'heading', 'attrs' => ['id' => 'id-h', 'level' => 2], 'content' => [['type' => 'text', 'text' => 'Cooling basics']]],
    ]];

    $new = ['type' => 'doc', 'content' => [
        // Same text as the old HEADING but a paragraph — type mismatch, no match.
        rematchParagraph(null, 'Cooling basics'),
        // Below-threshold similarity — no match.
        rematchParagraph(null, 'A brand new paragraph about thermometers.'),
    ]];

    $rematched = (new NodeIdRematcher)->rematch($old, $new);

    expect($rematched['content'][0]['attrs']['id'] ?? null)->toBeNull()
        ->and($rematched['content'][1]['attrs']['id'] ?? null)->toBeNull();
});
