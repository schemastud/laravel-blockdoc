<?php

use Rushing\BlockSchema\Rendering\ProseHtmlParser;
use Rushing\BlockSchema\Rendering\ProseHtmlSerializer;

function proseRoundtrip(string $html): string
{
    $nodes = (new ProseHtmlParser)->parse($html);

    return (new ProseHtmlSerializer)->fragment($nodes);
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
