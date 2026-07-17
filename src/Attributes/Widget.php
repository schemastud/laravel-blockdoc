<?php

namespace Schemastud\Blockdoc\Attributes;

use Attribute;

/**
 * Declares an override widget for a Block property — the server emits it as the
 * property's `x-widget` key inside the node's `attrsSchema` (override-only,
 * ED-15). Absent this attribute nothing is emitted and the property keeps its
 * inferred control. Drives the seam widgets (button-group / star-rating, ED-02).
 *
 *   #[NodeAttr] #[Widget('star-rating')]
 *   public int $rating = 0;
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Widget
{
    public function __construct(public string $name) {}
}
