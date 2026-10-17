<?php

namespace Schemastud\Blockdoc\Rendering;

use Schemastud\Blockdoc\Contracts\Node;

/**
 * Serializes standard prose nodes (p, text + marks, h1..h6, lists, blockquote, br)
 * to HTML. Embed Nodes are handled by the renderer, not here — this only walks prose
 * subtrees. The inverse of {@see ProseHtmlParser}. ADR-0228 d6: a node's name is its
 * JSX tag, so each prose node serializes to the element of the same name.
 */
class ProseHtmlSerializer
{
    /**
     * @param  list<Node>  $nodes
     */
    public function fragment(array $nodes): string
    {
        return implode('', array_map(fn (Node $node): string => $this->node($node), $nodes));
    }

    public function node(Node $node): string
    {
        return match ($node->type()) {
            'text' => $this->text($node),
            'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote' => $this->wrap($node->type(), $node),
            'br' => '<br>',
            default => $this->fragment($node->content()),
        };
    }

    private function wrap(string $tag, Node $node, string $attributes = ''): string
    {
        return "<{$tag}{$attributes}>".$this->fragment($node->content())."</{$tag}>";
    }

    private function text(Node $node): string
    {
        $text = e($this->textValue($node));

        foreach ($this->marks($node) as $mark) {
            $text = $this->applyMark($mark, $text);
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $mark
     */
    private function applyMark(array $mark, string $inner): string
    {
        return match ($mark['type'] ?? null) {
            'strong', 'bold' => "<strong>{$inner}</strong>",
            'em', 'italic' => "<em>{$inner}</em>",
            'code' => "<code>{$inner}</code>",
            'link' => sprintf('<a href="%s">%s</a>', e($mark['attrs']['href'] ?? '#'), $inner),
            default => $inner,
        };
    }

    private function textValue(Node $node): string
    {
        return method_exists($node, 'text') ? (string) $node->text() : '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function marks(Node $node): array
    {
        return method_exists($node, 'marks') ? $node->marks() : [];
    }
}
