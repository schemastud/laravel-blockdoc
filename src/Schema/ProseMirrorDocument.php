<?php

namespace Schemastud\Blockdoc\Schema;

use Schemastud\Blockdoc\Contracts\Document;
use Schemastud\Blockdoc\Contracts\Node;

class ProseMirrorDocument implements Document
{
    /** @param list<Node> $nodes */
    public function __construct(private array $nodes) {}

    public function content(): array
    {
        return $this->nodes;
    }

    public function toArray(): array
    {
        return [
            'type' => 'doc',
            'content' => array_map(fn (Node $n) => $n->toArray(), $this->nodes),
        ];
    }
}
