<?php

namespace Schemastud\Blockdoc\Attributes;

use Attribute;

/**
 * Declares an override skin for a Block's resting node-view — the server emits it
 * as the node-manifest entry's `x-skin` key (override-only, ED-15). Absent this
 * attribute nothing is emitted and the client resolves the node-type-default skin
 * (frame's `resolveSkinFor`, ED-12). The manifest declares only the *key*; the
 * client skin registry maps key → component (the server can't ship React).
 *
 *   #[NodeType('product')]
 *   #[Skin('product-card')]
 *   class ProductBlock extends Block { … }
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Skin
{
    public function __construct(public string $name) {}
}
