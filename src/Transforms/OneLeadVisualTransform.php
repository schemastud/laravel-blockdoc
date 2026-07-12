<?php

namespace Schemastud\Blockdoc\Transforms;

use Schemastud\Blockdoc\Contracts\DocumentTransform;

class OneLeadVisualTransform implements DocumentTransform
{
    public function transform(array $input, array $context = []): array
    {
        foreach ($input['sections'] ?? [] as $i => $section) {
            if (! $this->hasEmbed($section['content'] ?? [], 'expedia_teaser')) {
                continue;
            }

            $input['sections'][$i]['content'] = array_values(array_filter(
                $section['content'],
                fn (array $item): bool => ($item['embed']['type'] ?? null) !== 'image_float',
            ));
        }

        return $input;
    }

    /** @param list<array<string, mixed>> $content */
    private function hasEmbed(array $content, string $type): bool
    {
        foreach ($content as $item) {
            if (($item['embed']['type'] ?? null) === $type) {
                return true;
            }
        }

        return false;
    }
}
