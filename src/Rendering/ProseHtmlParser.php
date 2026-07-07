<?php

namespace Rushing\BlockSchema\Rendering;

use DOMElement;
use DOMNode;
use DOMText;
use Rushing\BlockSchema\Contracts\Node;
use Rushing\BlockSchema\Nodes\GenericNode;

/**
 * Parses an HTML prose fragment into ProseMirror prose Nodes — the inverse of
 * the prose serializer. LLM output (or legacy body HTML) is normalised into
 * paragraph/text/marks/list Nodes so it is stored as genuine block-document content
 * (ADR-0019), not an opaque HTML string in attrs.
 */
class ProseHtmlParser
{
    /**
     * @return list<Node> block-level prose nodes
     */
    public function parse(string $html): array
    {
        $html = trim($html);

        if ($html === '') {
            return [];
        }

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();

        $root = $dom->getElementById('__root');

        if ($root === null) {
            return [];
        }

        $nodes = [];

        foreach ($root->childNodes as $child) {
            $node = $this->block($child);

            if ($node !== null) {
                $nodes[] = $node;
            }
        }

        return $this->mergeFragmentedParagraphs($nodes);
    }

    /**
     * Heal a common LLM failure: a single sentence emitted as several adjacent
     * `<p>` fragments (e.g. "The world of" / "Game of Thrones" / "has captivated us.").
     * A paragraph is folded into the previous one when the previous does not end on
     * sentence-ending punctuation.
     *
     * @param  list<Node>  $nodes
     * @return list<Node>
     */
    private function mergeFragmentedParagraphs(array $nodes): array
    {
        $result = [];

        foreach ($nodes as $node) {
            $prev = $result === [] ? null : $result[count($result) - 1];

            if ($node->type() === 'paragraph'
                && $prev instanceof Node
                && $prev->type() === 'paragraph'
                && ! $this->endsSentence($this->plainText($prev))
            ) {
                $children = $prev->content();
                $text = $this->plainText($node);

                if ($text !== '' && ! str_contains('.,;:!?)', $text[0])) {
                    $children[] = new GenericNode('text', [], [], ' ');
                }

                $result[count($result) - 1] = new GenericNode('paragraph', [], [...$children, ...$node->content()]);

                continue;
            }

            $result[] = $node;
        }

        return $result;
    }

    private function plainText(Node $node): string
    {
        if ($node instanceof GenericNode && $node->text() !== null) {
            return $node->text();
        }

        return array_reduce(
            $node->content(),
            fn (string $carry, Node $child): string => $carry.$this->plainText($child),
            '',
        );
    }

    private function endsSentence(string $text): bool
    {
        $text = rtrim($text);

        return $text === '' || (bool) preg_match('/[.!?:;"\')\]]$/u', $text);
    }

    private function block(DOMNode $node): ?Node
    {
        // Bare text directly under the fragment root becomes a paragraph.
        if ($node instanceof DOMText) {
            $text = trim($node->wholeText);

            return $text === '' ? null : new GenericNode('paragraph', [], [new GenericNode('text', [], [], $text)]);
        }

        if (! $node instanceof DOMElement) {
            return null;
        }

        return match (strtolower($node->tagName)) {
            'p' => new GenericNode('paragraph', [], $this->inline($node)),
            'h2', 'h3', 'h4', 'h5', 'h6' => new GenericNode(
                'heading',
                ['level' => (int) substr(strtolower($node->tagName), 1)],
                $this->inline($node),
            ),
            'ul' => new GenericNode('bullet_list', [], $this->listItems($node)),
            'ol' => new GenericNode('ordered_list', [], $this->listItems($node)),
            'blockquote' => new GenericNode('blockquote', [], $this->inline($node)),
            default => new GenericNode('paragraph', [], $this->inline($node)),
        };
    }

    /**
     * @return list<Node>
     */
    private function listItems(DOMElement $list): array
    {
        $items = [];

        foreach ($list->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->tagName) === 'li') {
                $items[] = new GenericNode('list_item', [], $this->inline($child));
            }
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $marks
     * @return list<Node>
     */
    private function inline(DOMNode $node, array $marks = []): array
    {
        $out = [];

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                if ($child->wholeText !== '') {
                    $out[] = new GenericNode('text', [], [], $child->wholeText, $marks);
                }

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if ($tag === 'br') {
                $out[] = new GenericNode('hard_break');

                continue;
            }

            $childMarks = match ($tag) {
                'strong', 'b' => [...$marks, ['type' => 'strong']],
                'em', 'i' => [...$marks, ['type' => 'em']],
                'code' => [...$marks, ['type' => 'code']],
                'a' => [...$marks, ['type' => 'link', 'attrs' => ['href' => $child->getAttribute('href')]]],
                default => $marks,
            };

            $out = [...$out, ...$this->inline($child, $childMarks)];
        }

        return $out;
    }
}
