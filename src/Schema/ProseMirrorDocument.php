<?php

namespace Rushing\BlockSchema\Schema;

use Rushing\BlockSchema\Contracts\Document;
use Rushing\BlockSchema\Contracts\Node;

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
