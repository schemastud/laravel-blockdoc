<?php

namespace Schemastud\BlockSchema\Contracts;

interface Document
{
    /** @return list<Node> */
    public function content(): array;

    /** @return array<string, mixed> ProseMirror-format doc array */
    public function toArray(): array;
}
