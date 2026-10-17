<?php

use Schemastud\Blockdoc\Rendering\LegacyProseNodes;

/**
 * EP-07c shared rename table. The PHP serializer, the schemastud/js editor's
 * normalize-on-load, and EP-07c B's data migration must all agree on exactly which
 * pre-07c name maps to which post-07c name. This asserts the canonical PHP copy; the
 * JS and migration copies carry the identical table and assert it the same way (the
 * PROOF shows the three are byte-identical).
 */
it('maps every pre-07c prose node name to its post-07c JSX tag', function () {
    expect(LegacyProseNodes::RENAME)->toBe([
        'paragraph' => 'p',
        'bullet_list' => 'ul',
        'ordered_list' => 'ol',
        'list_item' => 'li',
        'code_block' => 'pre',
        'horizontal_rule' => 'hr',
        'hard_break' => 'br',
    ]);
});

it('maps a pre-07c heading level to h2..h6 (h2 floor, h6 ceiling, default 3)', function () {
    expect(LegacyProseNodes::headingTag(1))->toBe('h2')   // floor: the page title owns h1
        ->and(LegacyProseNodes::headingTag(2))->toBe('h2')
        ->and(LegacyProseNodes::headingTag(3))->toBe('h3')
        ->and(LegacyProseNodes::headingTag(4))->toBe('h4')
        ->and(LegacyProseNodes::headingTag(5))->toBe('h5')
        ->and(LegacyProseNodes::headingTag(6))->toBe('h6')
        ->and(LegacyProseNodes::headingTag(7))->toBe('h6')   // ceiling
        ->and(LegacyProseNodes::headingTag(0))->toBe('h2');
});
