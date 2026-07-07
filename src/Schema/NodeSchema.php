<?php

namespace Rushing\BlockSchema\Schema;

use Rushing\BlockSchema\Attributes\NodeType;
use Rushing\BlockSchema\Blocks\Block;
use Rushing\BlockSchema\Contracts\Schema;

class NodeSchema implements Schema
{
    /** @var array<string, class-string<Block>> */
    private array $map = [];

    public function register(string $type, string $class): void
    {
        $this->map[$type] = $class;
    }

    public function registerClass(string $class): void
    {
        $attrs = (new \ReflectionClass($class))->getAttributes(NodeType::class);

        if (empty($attrs)) {
            throw new \LogicException($class.' must have a #[NodeType] attribute to be registered.');
        }

        $this->register($attrs[0]->newInstance()->name, $class);
    }

    public function has(string $type): bool
    {
        return isset($this->map[$type]);
    }

    public function resolve(string $type): string
    {
        return $this->map[$type]
            ?? throw new \InvalidArgumentException("Unknown node type [{$type}].");
    }
}
