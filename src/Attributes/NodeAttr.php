<?php

namespace Rushing\BlockSchema\Attributes;

#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
class NodeAttr
{
    public function __construct(
        public ?string $description = null,
        public mixed $example = null,
        public bool $required = true,
        public mixed $default = null,
    ) {}
}
