<?php

namespace Schemastud\BlockSchema\Contracts;

use Schemastud\BlockSchema\Blocks\Block;
use Schemastud\BlockSchema\Schema\ManifestBuilder;

/**
 * The host's declaration of which Block classes make up each manifest profile.
 * The package walks `#[NodeType]` Blocks; only the host knows which Blocks belong
 * to which editing profile (and what the profile's document admits), so hosts
 * bind this contract and the {@see ManifestBuilder}
 * / `block-schema:export-manifest` consume it.
 */
interface ManifestNodeSource
{
    /**
     * The profile names this source can build manifests for.
     *
     * @return list<string>
     */
    public function profiles(): array;

    /**
     * The `#[NodeType]` Block classes registered for a profile. A profile whose
     * vocabulary has a ROOT block (the document itself, e.g. an article) must NOT
     * list it here — the root maps to the manifest's `doc` entry via
     * {@see self::docAdmitsFor()}.
     *
     * @return list<class-string<Block>>
     */
    public function nodeClassesFor(string $profile): array;

    /**
     * What the profile's document node admits, same semantics as a node's
     * admitsChildCategories: null = unconstrained, [] = leaf, list = category union.
     * For a vocabulary with a root block this is the root's admitsChildCategories.
     * Raw category `$id`s are fine — the builder emits their PM-safe slugs.
     *
     * @return list<string>|null
     */
    public function docAdmitsFor(string $profile): ?array;

    /**
     * Additional mark declarations for a profile. Profile manifests do NOT repeat
     * the base marks (strong/em/code/link/annotation live in the canonical base
     * manifest), so the default answer is an empty list.
     *
     * @return list<array<string, mixed>>
     */
    public function marksFor(string $profile): array;
}
