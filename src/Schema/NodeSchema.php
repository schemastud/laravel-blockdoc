<?php

namespace Schemastud\Blockdoc\Schema;

use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\OnDuplicate;
use Rushing\Popcorn\Registries\RegistryArity;
use Schemastud\Blockdoc\Attributes\NodeType;
use Schemastud\Blockdoc\Blocks\Block;
use Schemastud\Blockdoc\Contracts\Schema;

#[IsRegistry(
    root: 'blockdoc.node-types',
    of: 'ProseMirror node types — nodeType slug => Block class, read by the DocumentHydrator',
    arity: RegistryArity::PickOne,
    onDuplicate: OnDuplicate::Supersede,
    note: 'Nothing SCANS here, despite registerClass() reading #[NodeType] off the class it is handed: '
        .'the host chooses which Block classes exist and hands each one over from its provider, and the '
        .'attribute only supplies the slug. A miss THROWS rather than falling back to a generic node — '
        .'a document containing a type the app never registered is corrupt, not degraded.',
    order: 50,
)]
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
