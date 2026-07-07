<?php

namespace Rushing\BlockSchema\Transforms;

use Rushing\BlockSchema\Contracts\DocumentTransform;

class DedupeImagesTransform implements DocumentTransform
{
    public function transform(array $input, array $context = []): array
    {
        $used = [];

        foreach ($input['sections'] ?? [] as $i => $section) {
            $input['sections'][$i]['content'] = array_values(array_filter(
                $section['content'] ?? [],
                function (array $item) use (&$used): bool {
                    if (($item['embed']['type'] ?? null) !== 'image_float') {
                        return true;
                    }

                    $src = $item['embed']['src'] ?? null;

                    if ($src === null || isset($used[$src])) {
                        return false;
                    }

                    $used[$src] = true;

                    return true;
                },
            ));
        }

        return $input;
    }
}
