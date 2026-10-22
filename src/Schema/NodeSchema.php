<?php

namespace Schemastud\Blockdoc\Schema;

use Rushing\Popcorn\Registries\Authorizer;
use Rushing\Popcorn\Registries\BasicRegistry;
use Rushing\Popcorn\Registries\Gated;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\OnKeyDuplicate;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryKey;
use Schemastud\Blockdoc\Attributes\NodeType;
use Schemastud\Blockdoc\Blocks\Block;
use Schemastud\Blockdoc\Contracts\Schema;

#[IsRegistry(
    root: 'blockdoc.node-types',
    entryType: 'class-string<'.Block::class.'>',
    onKeyDuplicate: OnKeyDuplicate::Supersede,
    description: 'ProseMirror node types — nodeType slug => Block class, read by the DocumentHydrator. Nothing SCANS here, despite registerClass() reading #[NodeType] off the class it is handed: the host chooses which Block classes exist and hands each one over from its provider, and the attribute only supplies the slug. A miss THROWS rather than falling back to a generic node — a document containing a type the app never registered is corrupt, not degraded.',
    order: 50,
)]
class NodeSchema implements Gated, Schema
{
    /**
     * The reserved prefix of a node type spelled as a JSX tag. popcorn keys are lowercase dot-separated segments and are
     * never folded (Key: "it validates; it never repairs"), but the composition-slot rename made node types PascalCase
     * tags (`ContentOutline`). Such a tag is keyed by one reversible segment, `tag:` plus its lower-cased words joined
     * by `:` (`tag:content:outline`), and every public method here still speaks the tag.
     */
    public const TAG_PREFIX = 'tag:';

    /** @var BasicRegistry<class-string<Block>> */
    private BasicRegistry $entries;

    /**
     * The registry key for a node type: a legal key as-is, a PascalCase tag by its reversible encoding.
     * `ContentOutline` → `tag:content:outline`, `HTMLBlock` → `tag:h:t:m:l:block`, `faq_item` → `faq_item`.
     */
    public static function keyFor(string $type): string
    {
        if (str_starts_with($type, self::TAG_PREFIX)) {
            throw new \InvalidArgumentException("Node type [{$type}] spells the reserved prefix [".self::TAG_PREFIX.'] that NodeSchema keys JSX tags by.');
        }

        if (preg_match('/^(?:[A-Z][a-z0-9]*)+$/', $type) !== 1) {
            return $type;
        }

        preg_match_all('/[A-Z][a-z0-9]*/', $type, $words);

        return self::TAG_PREFIX.implode(':', array_map('strtolower', $words[0]));
    }

    /** The node type a registry key names: the inverse of {@see keyFor()}. */
    public static function typeFor(string $key): string
    {
        if (! str_starts_with($key, self::TAG_PREFIX)) {
            return $key;
        }

        return implode('', array_map('ucfirst', explode(':', substr($key, strlen(self::TAG_PREFIX)))));
    }

    private static function key(RegistryKey|string $key): RegistryKey|string
    {
        return is_string($key) ? self::keyFor($key) : $key;
    }

    public function __construct()
    {
        $this->entries = BasicRegistry::for($this);
    }

    /**
     * @param  class-string<Block>|null  $entry
     */
    public function register(RegistryKey|string $key, mixed $entry = null, ?string $by = null, ?string $ability = null): static
    {
        $this->entries->register(self::key($key), $entry, $by, $ability);

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
        return array_map(self::typeFor(...), $this->entries->relativeKeys());
    }

    public function has(RegistryKey|string $key): bool
    {
        return $this->entries->has(self::key($key));
    }

    public function resolve(RegistryKey|string $key): mixed
    {
        return $this->entries->resolve(self::key($key));
    }

    public function tryResolve(RegistryKey|string $key): mixed
    {
        return $this->entries->tryResolve(self::key($key));
    }

    public function matches(RegistryKey|string $key): array
    {
        return $this->entries->matches(self::key($key));
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
