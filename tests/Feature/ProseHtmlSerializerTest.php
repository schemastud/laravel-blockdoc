<?php

use Schemastud\Blockdoc\Nodes\GenericNode;
use Schemastud\Blockdoc\Rendering\ProseHtmlParser;
use Schemastud\Blockdoc\Rendering\ProseHtmlSerializer;

function proseRoundtrip(string $html): string
{
    $nodes = (new ProseHtmlParser)->parse($html);

    return (new ProseHtmlSerializer)->fragment($nodes);
}

function serializeNodes(GenericNode ...$nodes): string
{
    return (new ProseHtmlSerializer)->fragment($nodes);
}

function textNode(string $text): GenericNode
{
    return new GenericNode('text', [], [], $text);
}

it('serializes a paragraph with text', function () {
    expect(proseRoundtrip('<p>Hello world.</p>'))->toBe('<p>Hello world.</p>');
});

it('escapes text content', function () {
    expect(proseRoundtrip('<p>a &amp; b &lt; c</p>'))->toBe('<p>a &amp; b &lt; c</p>');
});

it('serializes headings clamped to h2-h6', function () {
    expect(proseRoundtrip('<h2>Title</h2>'))->toBe('<h2>Title</h2>')
        ->and(proseRoundtrip('<h4>Sub</h4>'))->toBe('<h4>Sub</h4>');
});

it('serializes bullet and ordered lists', function () {
    expect(proseRoundtrip('<ul><li>one</li><li>two</li></ul>'))
        ->toBe('<ul><li>one</li><li>two</li></ul>')
        ->and(proseRoundtrip('<ol><li>first</li></ol>'))
        ->toBe('<ol><li>first</li></ol>');
});

it('serializes a blockquote (parser flattens the inner paragraph to text)', function () {
    expect(proseRoundtrip('<blockquote><p>quoted</p></blockquote>'))
        ->toBe('<blockquote>quoted</blockquote>');
});

it('round-trips strong and em marks', function () {
    expect(proseRoundtrip('<p><strong>bold</strong> and <em>italic</em></p>'))
        ->toBe('<p><strong>bold</strong> and <em>italic</em></p>');
});

it('round-trips a link mark', function () {
    expect(proseRoundtrip('<p><a href="https://example.test">link</a></p>'))
        ->toBe('<p><a href="https://example.test">link</a></p>');
});

/**
 * EP-07c READ-COMPAT: a document stored before EP-07c B's migration still holds the
 * pre-07c node names. The serializer must render each to the SAME DOM as before, so a
 * served page is byte-identical whether or not the document has been migrated yet.
 */
it('READ-COMPAT: an un-migrated paragraph serializes to <p>', function () {
    expect(serializeNodes(new GenericNode('paragraph', [], [textNode('Hello.')])))->toBe('<p>Hello.</p>');
});

it('READ-COMPAT: an un-migrated heading keeps the h2 floor + h3 default', function () {
    expect(serializeNodes(new GenericNode('heading', ['level' => 2], [textNode('T')])))->toBe('<h2>T</h2>')
        ->and(serializeNodes(new GenericNode('heading', ['level' => 4], [textNode('T')])))->toBe('<h4>T</h4>')
        // level 1 floors to h2 (the page title owns the one h1), 7 clamps to h6.
        ->and(serializeNodes(new GenericNode('heading', ['level' => 1], [textNode('T')])))->toBe('<h2>T</h2>')
        ->and(serializeNodes(new GenericNode('heading', ['level' => 7], [textNode('T')])))->toBe('<h6>T</h6>')
        // a heading with no level attr defaults to h3, exactly as pre-07c.
        ->and(serializeNodes(new GenericNode('heading', [], [textNode('T')])))->toBe('<h3>T</h3>');
});

it('READ-COMPAT: an un-migrated bullet_list/ordered_list/list_item serialize to ul/ol/li', function () {
    $item = fn (): GenericNode => new GenericNode('list_item', [], [new GenericNode('paragraph', [], [textNode('x')])]);

    expect(serializeNodes(new GenericNode('bullet_list', [], [$item()])))->toBe('<ul><li><p>x</p></li></ul>')
        ->and(serializeNodes(new GenericNode('ordered_list', [], [$item()])))->toBe('<ol><li><p>x</p></li></ol>');
});

it('READ-COMPAT: an un-migrated hard_break serializes to <br>', function () {
    expect(serializeNodes(new GenericNode('paragraph', [], [textNode('a'), new GenericNode('hard_break'), textNode('b')])))
        ->toBe('<p>a<br>b</p>');
});
