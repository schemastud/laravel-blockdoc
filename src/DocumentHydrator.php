<?php

namespace Schemastud\Blockdoc;

use Schemastud\Blockdoc\Blocks\Block;
use Schemastud\Blockdoc\Contracts\Document;
use Schemastud\Blockdoc\Contracts\Node;
use Schemastud\Blockdoc\Contracts\Schema;
use Schemastud\Blockdoc\Nodes\GenericNode;
use Schemastud\Blockdoc\Schema\ProseMirrorDocument;

/**
 * The reverse of Document::toArray(): a recursive tree-walker that turns a
 * ProseMirror doc array back into typed Block Nodes. Spatie Data carries the
 * per-node typed hydration; this class only resolves types and recurses.
 */
class DocumentHydrator
{
    public function __construct(private Schema $schema) {}

    /**
     * @param  array{type?: string, content?: list<array<string, mixed>>}  $doc
     */
    public function hydrate(array $doc): Document
    {
        $nodes = array_map(
            fn (array $node): Node => $this->hydrateNode($node),
            $doc['content'] ?? [],
        );

        return new ProseMirrorDocument($nodes);
    }

    /**
     * @param  array{type: string, attrs?: array<string, mixed>, content?: list<array<string, mixed>>}  $node
     */
    private function hydrateNode(array $node): Node
    {
        // Types the Schema doesn't map to a typed Block are standard ProseMirror
        // prose (paragraph, text, marks, …) — passed through faithfully.
        if (! $this->schema->has($node['type'])) {
            return GenericNode::fromArray($node, fn (array $child): Node => $this->hydrateNode($child));
        }

        /** @var class-string<Block> $class */
        $class = $this->schema->resolve($node['type']);
        $attrs = $node['attrs'] ?? [];

        /** @var Block $block */
        $block = $class::from($attrs);
        $block->withId($attrs['id'] ?? null);

        $children = array_map(
            fn (array $child): Node => $this->hydrateNode($child),
            $node['content'] ?? [],
        );

        if ($children !== []) {
            $block->withContent($children);
        }

        return $block;
    }
}
