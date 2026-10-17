<?php

namespace Schemastud\Blockdoc\Rendering;

/**
 * EP-07c read-compat: the canonical map from the pre-07c base prose node names to
 * their post-07c JSX-tag names (ADR-0228 d6). A document stored before EP-07c B's
 * data migration still holds the old names; every READER (the PHP serializer here,
 * the schemastud/js editor's normalize-on-load, and B's migration) maps old → new
 * through THIS SAME table, so all three agree on exactly which names move where.
 *
 * Writers only ever emit the new names, so editing an old document migrates it on
 * write — consistent with B. The whole table is removed once every host has run B's
 * migration (follow-up tracked in ticket 00).
 *
 * The JS normalizer and B's migration each carry a byte-identical copy of this map
 * ({@see resources/manifests/legacy-node-rename.json}); each side's test asserts its
 * code against that fixture, and the PROOF shows the three copies are identical.
 */
final class LegacyProseNodes
{
    /**
     * Pre-07c name => post-07c name, for the level-independent prose nodes.
     *
     * `heading` is level-dependent and handled by {@see self::headingTag()}; a node
     * whose name is not a key here is already a current name (or a typed component)
     * and passes through unchanged.
     *
     * @var array<string, string>
     */
    public const RENAME = [
        'paragraph' => 'p',
        'bullet_list' => 'ul',
        'ordered_list' => 'ol',
        'list_item' => 'li',
        'code_block' => 'pre',
        'horizontal_rule' => 'hr',
        'hard_break' => 'br',
    ];

    /**
     * A pre-07c `heading` carried its level as an attr; post-07c the level IS the tag.
     * The level is clamped to h2..h6 (floor 2, default 3) — the deliberate pre-07c
     * behaviour (the parser never produced h1 and the serializer floored to h2, since
     * the page title owns the one h1), matching B's migration exactly.
     */
    public static function headingTag(int $level): string
    {
        return 'h'.max(2, min(6, $level));
    }
}
