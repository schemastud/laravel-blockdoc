<?php

namespace Schemastud\Blockdoc\Rendering;

use Schemastud\Blockdoc\Contracts\Node;

/**
 * Serializes standard ProseMirror prose nodes (paragraph, text + marks, headings,
 * lists, blockquote, hard_break) to HTML. Embed Nodes are handled by the renderer,
 * not here — this only walks prose subtrees. The inverse of {@see ProseHtmlParser}.
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
            'paragraph' => $this->wrap('p', $node),
            'heading' => $this->heading($node),
            'bullet_list' => $this->wrap('ul', $node),
            'ordered_list' => $this->wrap('ol', $node),
            'list_item' => $this->wrap('li', $node),
            'blockquote' => $this->wrap('blockquote', $node),
            'hard_break' => '<br>',
            default => $this->fragment($node->content()),
        };
    }

    private function wrap(string $tag, Node $node, string $attributes = ''): string
    {
        return "<{$tag}{$attributes}>".$this->fragment($node->content())."</{$tag}>";
    }

    private function heading(Node $node): string
    {
        $level = (int) ($node->attrs()['level'] ?? 3);
        $level = max(2, min(6, $level));

        return $this->wrap("h{$level}", $node);
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
