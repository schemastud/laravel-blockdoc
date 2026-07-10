<?php

namespace Rushing\BlockSchema\Contracts;

use Rushing\BlockSchema\Blocks\Block;
use Rushing\BlockSchema\Schema\ManifestBuilder;

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
     * The `#[NodeType]` Block classes registered for a profile.
     *
     * @return list<class-string<Block>>
     */
    public function nodeClassesFor(string $profile): array;

    /**
     * What the profile's document node admits, same semantics as a node's
     * admitsChildCategories: null = unconstrained, [] = leaf, list = category union.
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
