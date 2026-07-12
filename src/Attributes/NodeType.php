<?php

namespace Schemastud\BlockSchema\Attributes;

use Schemastud\BlockSchema\Blocks\Block;

/**
 * Declares a block's ProseMirror node type. The former `content` expression
 * (`'block*'`/`'inline*'`) was inert — nothing parsed or enforced it — so it was
 * retired; containment is now expressed by category admission on the block itself
 * ({@see Block::admitsChildCategories()}).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class NodeType
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public string $group = 'block',
    ) {}
}
