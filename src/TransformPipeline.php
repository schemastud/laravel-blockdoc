<?php

namespace Schemastud\Blockdoc;

use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\OnDuplicate;
use Rushing\Popcorn\Registries\RegistryArity;
use Schemastud\Blockdoc\Contracts\DocumentTransform;

/**
 * Ordered registry of {@see DocumentTransform} class-strings. Structural transforms are
 * registered by the package ServiceProvider; grounding-driven transforms are appended by
 * the consuming app (ADR-0021 registry/config seam).
 */
#[IsRegistry(
    root: 'blockdoc.transforms',
    of: 'document transforms applied to a ProseMirror doc in registration order (structural first, then host/grounding-driven)',
    arity: RegistryArity::ComposeMany,
    onDuplicate: OnDuplicate::Admit,
    note: 'ComposeMany, not RunAll: transform() threads the document through every registered transform '
        .'in turn, each receiving the previous one\'s output. Order is not merely significant, it IS the '
        .'semantics — ADR-0021 puts the package\'s structural transforms before the app\'s '
        .'grounding-driven ones for exactly that reason.',
    order: 51,
)]
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
