<?php

namespace Schemastud\BlockSchema\Contracts;

interface Node
{
    public function type(): string;

    /** @return array<string, mixed> The node attributes, always including the UUIDv7 `id`. */
    public function attrs(): array;

    /** @return list<Node> Zero or more child Nodes. */
    public function content(): array;

    /** @return array<string, mixed> ProseMirror-format node array `{type, attrs, content?}`. */
    public function toArray(): array;
}
