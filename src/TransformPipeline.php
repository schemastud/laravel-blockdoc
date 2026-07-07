<?php

namespace Rushing\BlockSchema;

use Rushing\BlockSchema\Contracts\DocumentTransform;

/**
 * Ordered registry of {@see DocumentTransform} class-strings. Structural transforms are
 * registered by the package ServiceProvider; grounding-driven transforms are appended by
 * the consuming app (ADR-0021 registry/config seam).
 */
class TransformPipeline
{
    /** @var list<class-string<DocumentTransform>> */
    private array $transforms = [];

    /** @param class-string<DocumentTransform> $class */
    public function register(string $class): void
    {
        $this->transforms[] = $class;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $context  Runtime data forwarded to each transform
     * @return array<string, mixed>
     */
    public function transform(array $input, array $context = []): array
    {
        foreach ($this->transforms as $class) {
            $input = app($class)->transform($input, $context);
        }

        return $input;
    }
}
