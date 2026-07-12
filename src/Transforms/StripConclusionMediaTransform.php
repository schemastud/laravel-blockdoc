<?php

namespace Schemastud\BlockSchema\Transforms;

use Schemastud\BlockSchema\Contracts\DocumentTransform;

class StripConclusionMediaTransform implements DocumentTransform
{
    public function transform(array $input, array $context = []): array
    {
        if (! isset($input['conclusion']['content'])) {
            return $input;
        }

        $input['conclusion']['content'] = array_values(array_filter(
            $input['conclusion']['content'],
            fn (array $item): bool => ($item['embed']['type'] ?? null) !== 'image_float',
        ));

        return $input;
    }
}
