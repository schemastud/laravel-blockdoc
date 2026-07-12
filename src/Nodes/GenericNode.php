<?php

namespace Schemastud\Blockdoc\Nodes;

use Schemastud\Blockdoc\Contracts\Node;

/**
 * A faithful passthrough for standard ProseMirror nodes (paragraph, text,
 * heading, lists, …) that are not modeled as typed Blocks. Round-trips its
 * raw structure losslessly so prose authored in a Tiptap-style editor survives
 * hydrate → serialize without a bespoke DTO per inline node.
 */
class GenericNode implements Node
{
    /**
     * @param  list<Node>  $children
     * @param  array<string, mixed>  $attrs
     * @param  list<array<string, mixed>>  $marks
     */
    public function __construct(
        private string $type,
        private array $attrs = [],
        private array $children = [],
        private ?string $text = null,
        private array $marks = [],
    ) {}

    /**
     * @param  array{type: string, attrs?: array<string, mixed>, content?: list<array<string, mixed>>, text?: string, marks?: list<array<string, mixed>>}  $node
     * @param  callable(array<string, mixed>): Node  $hydrateChild
     */
    public static function fromArray(array $node, callable $hydrateChild): self
    {
        return new self(
            type: $node['type'],
            attrs: $node['attrs'] ?? [],
            children: array_map($hydrateChild, $node['content'] ?? []),
            text: $node['text'] ?? null,
            marks: $node['marks'] ?? [],
        );
    }

    public function type(): string
    {
        return $this->type;
    }

    /** @return array<string, mixed> */
    public function attrs(): array
    {
        return $this->attrs;
    }

    /** @return list<Node> */
    public function content(): array
    {
        return $this->children;
    }

    public function text(): ?string
    {
        return $this->text;
    }

    /** @return list<array<string, mixed>> */
    public function marks(): array
    {
        return $this->marks;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $node = ['type' => $this->type];

        if ($this->attrs !== []) {
            $node['attrs'] = $this->attrs;
        }

        if ($this->text !== null) {
            $node['text'] = $this->text;
        }

        if ($this->marks !== []) {
            $node['marks'] = $this->marks;
        }

        if ($this->children !== []) {
            $node['content'] = array_map(fn (Node $child): array => $child->toArray(), $this->children);
        }

        return $node;
    }
}
