<?php

namespace Schemastud\Blockdoc;

use Schemastud\Blockdoc\Blocks\Block;
use Schemastud\Blockdoc\Contracts\Document;
use Schemastud\Blockdoc\Contracts\DocumentRenderer;
use Schemastud\Blockdoc\Contracts\Node;
use Schemastud\Blockdoc\Contracts\Schema;
use Schemastud\Blockdoc\Rendering\ProseHtmlParser;
use Schemastud\Blockdoc\Schema\ProseMirrorDocument;

/**
 * Assembles a typed block document from the structured shape produced by the
 * generation stages, and compiles it to the stored `posts.body_blocks` + the
 * derived `posts.body` HTML. This is the deterministic seam: generation only has
 * to emit ordered prose/embed data; placement, typing, and markup live here.
 *
 * Top-level block vocabulary (opening, section, faq, faq_item, conclusion) is
 * resolved from the Schema registry at runtime — the app registers its concrete
 * Block subclasses; the builder never imports them directly.
 *
 * Expected input shape:
 *   [
 *     'opening' => ['prose' => '<p>…</p>'],
 *     'sections' => [
 *       ['heading' => '…', 'content' => [
 *         ['prose' => '<p>…</p>'],
 *         ['embed' => ['type' => 'inset_poster', 'kind' => 'movie', 'slug' => '…']],
 *       ]],
 *     ],
 *     'faq' => [['question' => '…', 'prose' => '<p>…</p>']],
 *     'conclusion' => ['content' => [['prose' => '…'], ['embed' => […]]]],
 *   ]
 */
class BlockDocumentBuilder
{
    public function __construct(
        private ProseHtmlParser $parser = new ProseHtmlParser,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function build(array $data): Document
    {
        $schema = app(Schema::class);
        $blocks = [];

        if (! empty($data['opening'])) {
            /** @var class-string<Block> $class */
            $class = $schema->resolve('opening');
            $blocks[] = $class::from([])->withContent($this->prose($data['opening']['prose'] ?? ''));
        }

        foreach ($data['sections'] ?? [] as $section) {
            /** @var class-string<Block> $class */
            $class = $schema->resolve('section');
            $blocks[] = $class::from(['heading' => $section['heading'] ?? ''])
                ->withContent($this->content($section['content'] ?? [], $schema));
        }

        if (! empty($data['faq'])) {
            /** @var class-string<Block> $class */
            $class = $schema->resolve('faq');
            $blocks[] = $class::from([])->withContent($this->faqItems($data['faq'], $schema));
        }

        if (! empty($data['conclusion'])) {
            /** @var class-string<Block> $class */
            $class = $schema->resolve('conclusion');
            $blocks[] = $class::from([])->withContent($this->content($data['conclusion']['content'] ?? [], $schema));
        }

        return new ProseMirrorDocument($blocks);
    }

    /**
     * Compile to the stored document JSON and the derived HTML cache (ADR-0017): the
     * `body_blocks_html` cache is rendered from the document and never overwrites `posts.body`.
     *
     * @param  array<string, mixed>  $data
     * @return array{body_blocks: array<string, mixed>, body_blocks_html: string}
     */
    public function compile(array $data): array
    {
        $document = $this->build($data);

        return [
            'body_blocks' => $document->toArray(),
            'body_blocks_html' => app(DocumentRenderer::class)->render($document),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<Node>
     */
    private function faqItems(array $items, Schema $schema): array
    {
        /** @var class-string<Block> $class */
        $class = $schema->resolve('faq_item');

        return array_map(
            fn (array $item): Node => $class::from(['question' => $item['question'] ?? ''])
                ->withContent($this->prose($item['prose'] ?? '')),
            $items,
        );
    }

    /**
     * An ordered mix of prose fragments and embed Nodes.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<Node>
     */
    private function content(array $items, Schema $schema): array
    {
        $out = [];

        foreach ($items as $item) {
            if (isset($item['prose'])) {
                $out = [...$out, ...$this->prose($item['prose'])];
            } elseif (isset($item['embed']) && ($embed = $this->embed($item['embed'], $schema)) !== null) {
                $out[] = $embed;
            }
        }

        return $out;
    }

    /**
     * @return list<Node>
     */
    private function prose(string $html): array
    {
        return $this->parser->parse($html);
    }

    /**
     * Build a typed embed Node, or null when the generator gave an unknown type or
     * incomplete data for it (a botched embed is dropped, not fatal).
     *
     * @param  array<string, mixed>  $embed
     */
    private function embed(array $embed, Schema $schema): ?Node
    {
        $type = $embed['type'] ?? null;
        unset($embed['type']);

        if ($type === null || ! $schema->has($type)) {
            return null;
        }

        try {
            /** @var class-string<Block> $class */
            $class = $schema->resolve($type);

            return $class::from($embed);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
