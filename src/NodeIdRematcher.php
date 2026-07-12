<?php

namespace Schemastud\BlockSchema;

/**
 * The node-id round-trip safety net (splicewire-editor issue 09). LLM revision
 * paths must preserve the ids of surviving nodes; when generation output comes
 * back with ids dropped or mangled, this re-matcher walks the new document and
 * reattaches ids from the old document by content similarity — so evidence
 * bindings never silently detach from the prose they annotate. Ids already
 * present are kept; each old id is spent at most once; nodes with no adequate
 * match stay id-less (the hydration pass assigns fresh ids downstream).
 */
class NodeIdRematcher
{
    public function __construct(
        protected float $threshold = 0.6,
    ) {}

    /**
     * @param  array<string, mixed>  $oldDoc
     * @param  array<string, mixed>  $newDoc
     * @return array<string, mixed> the new doc with surviving ids reattached
     */
    public function rematch(array $oldDoc, array $newDoc): array
    {
        $old = [];
        $this->collect($oldDoc, $old);

        $used = [];
        $this->collectUsedIds($newDoc, $used);

        // Ids already carried by the new doc are taken; the pool is what's left.
        $pool = array_values(array_filter(
            $old,
            fn (array $candidate): bool => ! isset($used[$candidate['id']]),
        ));

        $newDoc['content'] = $this->rematchChildren((array) ($newDoc['content'] ?? []), $pool);

        return $newDoc;
    }

    /**
     * @param  list<array<string, mixed>>  $children
     * @param  list<array{id: string, type: string, text: string}>  $pool
     * @return list<array<string, mixed>>
     */
    protected function rematchChildren(array $children, array &$pool): array
    {
        foreach ($children as $i => $node) {
            if (! is_array($node)) {
                continue;
            }

            if (! isset($node['attrs']['id']) || $node['attrs']['id'] === null) {
                $match = $this->bestMatch($node, $pool);

                if ($match !== null) {
                    $node['attrs'] = array_merge((array) ($node['attrs'] ?? []), ['id' => $match]);
                }
            }

            if (isset($node['content']) && is_array($node['content'])) {
                $node['content'] = $this->rematchChildren($node['content'], $pool);
            }

            $children[$i] = $node;
        }

        return $children;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<array{id: string, type: string, text: string}>  $pool
     */
    protected function bestMatch(array $node, array &$pool): ?string
    {
        $text = $this->plainText($node);

        if ($text === '') {
            return null;
        }

        $bestIndex = null;
        $bestScore = 0.0;

        foreach ($pool as $index => $candidate) {
            if ($candidate['type'] !== ($node['type'] ?? null) || $candidate['text'] === '') {
                continue;
            }

            similar_text($text, $candidate['text'], $percent);
            $score = $percent / 100;

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $index;
            }
        }

        if ($bestIndex === null || $bestScore < $this->threshold) {
            return null;
        }

        $id = $pool[$bestIndex]['id'];
        array_splice($pool, $bestIndex, 1); // each old id is spent once

        return $id;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<array{id: string, type: string, text: string}>  $collected
     */
    protected function collect(array $node, array &$collected): void
    {
        $id = $node['attrs']['id'] ?? null;

        if (is_string($id)) {
            $collected[] = [
                'id' => $id,
                'type' => (string) ($node['type'] ?? ''),
                'text' => $this->plainText($node),
            ];
        }

        foreach ((array) ($node['content'] ?? []) as $child) {
            if (is_array($child)) {
                $this->collect($child, $collected);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, true>  $used
     */
    protected function collectUsedIds(array $node, array &$used): void
    {
        $id = $node['attrs']['id'] ?? null;

        if (is_string($id)) {
            $used[$id] = true;
        }

        foreach ((array) ($node['content'] ?? []) as $child) {
            if (is_array($child)) {
                $this->collectUsedIds($child, $used);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    protected function plainText(array $node): string
    {
        $parts = [];

        if (isset($node['text']) && is_string($node['text'])) {
            $parts[] = $node['text'];
        }

        foreach (['heading', 'body'] as $attr) {
            if (isset($node['attrs'][$attr]) && is_string($node['attrs'][$attr])) {
                $parts[] = $node['attrs'][$attr];
            }
        }

        foreach ((array) ($node['content'] ?? []) as $child) {
            if (is_array($child)) {
                $parts[] = $this->plainText($child);
            }
        }

        return trim(implode(' ', array_filter($parts)));
    }
}
