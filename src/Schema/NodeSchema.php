<?php

namespace Schemastud\Blockdoc\Schema;

use Rushing\Popcorn\Registries\Authorizer;
use Rushing\Popcorn\Registries\BasicRegistry;
use Rushing\Popcorn\Registries\Gated;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\OnDuplicate;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryKey;
use Schemastud\Blockdoc\Attributes\NodeType;
use Schemastud\Blockdoc\Blocks\Block;
use Schemastud\Blockdoc\Contracts\Schema;

#[IsRegistry(
    root: 'blockdoc.node-types',
    entryType: 'class-string<'.Block::class.'>',
    onDuplicate: OnDuplicate::Supersede,
    description: 'ProseMirror node types — nodeType slug => Block class, read by the DocumentHydrator. Nothing SCANS here, despite registerClass() reading #[NodeType] off the class it is handed: the host chooses which Block classes exist and hands each one over from its provider, and the attribute only supplies the slug. A miss THROWS rather than falling back to a generic node — a document containing a type the app never registered is corrupt, not degraded.',
    order: 50,
)]
class NodeSchema implements Gated, Schema
{
    /** @var BasicRegistry<class-string<Block>> */
    private BasicRegistry $entries;

    public function __construct()
    {
        $this->entries = BasicRegistry::for($this);
    }

    /**
     * @param  class-string<Block>|null  $entry
     */
    public function register(RegistryKey|string $key, mixed $entry = null, ?string $by = null, ?string $ability = null): static
    {
        $this->entries->register($key, $entry, $by, $ability);

        return $this;
    }

    public function registerClass(string $class): void
    {
        $attrs = (new \ReflectionClass($class))->getAttributes(NodeType::class);

        if (empty($attrs)) {
            throw new \LogicException($class.' must have a #[NodeType] attribute to be registered.');
        }

        $this->register($attrs[0]->newInstance()->name, $class);
    }

    /**
     * The registered node-type slugs, as callers spelled them — {@see keys()} with the declared root
     * stripped back off, because keys go relative in and absolute out (ticket 20 D2).
     *
     * @return string[]
     */
    public function types(): array
    {
        return $this->entries->relativeKeys();
    }

    public function has(RegistryKey|string $key): bool
    {
        return $this->entries->has($key);
    }

    public function resolve(RegistryKey|string $key): mixed
    {
        return $this->entries->resolve($key);
    }

    public function tryResolve(RegistryKey|string $key): mixed
    {
        return $this->entries->tryResolve($key);
    }

    public function matches(RegistryKey|string $key): array
    {
        return $this->entries->matches($key);
    }

    public function keys(): array
    {
        return $this->entries->keys();
    }

    public function unfiltered(): Registry
    {
        return $this->entries->unfiltered();
    }

    public function authorizeWith(?Authorizer $authorizer): static
    {
        $this->entries->authorizeWith($authorizer);

        return $this;
    }
}
