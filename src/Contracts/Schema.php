<?php

namespace Schemastud\Blockdoc\Contracts;

use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryKey;
use Schemastud\Blockdoc\Blocks\Block;

/**
 * The node-type registry. The app populates it; the DocumentHydrator reads it.
 *
 * The contract EXTENDS the kernel's {@see Registry} rather than duplicating its verbs, so a node-type
 * table is addressable as a registry (and describable into the index) wherever one is expected. The
 * two domain verbs that are not the kernel's — {@see registerClass()}, which reads the slug off the
 * `#[NodeType]` attribute — stay, because they are the vocabulary this package's consumers speak.
 *
 * No `#[IsRegistry]` sits here on purpose: a declaration on an interface governs the interface and
 * never its implementers, and a contract handing one root to every implementer would manufacture the
 * root collision the attribute exists to make detectable. The root is declared on the concrete the
 * container binds, {@see \Schemastud\Blockdoc\Schema\NodeSchema}.
 *
 * @extends Registry<class-string<Block>>
 */
interface Schema extends Registry
{
    /**
     * Map a node-type slug to the Block class that hydrates it.
     *
     * WIDENED from {@see Registry::register()} — the entry defaults to null so the historical
     * two-argument call `register('section', SectionBlock::class)` keeps working unchanged.
     *
     * @param  class-string<Block>|null  $entry
     */
    public function register(RegistryKey|string $key, mixed $entry = null, ?string $by = null, ?string $ability = null): static;

    /**
     * Register a Block class by reading its #[NodeType] attribute name.
     *
     * @param  class-string<Block>  $class
     */
    public function registerClass(string $class): void;

    /**
     * The Block class registered for a node-type slug.
     *
     * @return class-string<Block>
     */
    public function resolve(RegistryKey|string $key): mixed;
}
