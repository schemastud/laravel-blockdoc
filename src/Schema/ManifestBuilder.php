<?php

namespace Schemastud\Blockdoc\Schema;

use Illuminate\Support\Str;
use ReflectionClass;
use Schemastud\Blockdoc\Attributes\NodeType;
use Schemastud\Blockdoc\Attributes\Skin;
use Schemastud\Blockdoc\Attributes\Widget;
use Schemastud\Blockdoc\Blocks\Block;
use Schemastud\Blockdoc\Contracts\DeclaresCodeAdmission;
use Schemastud\Blockdoc\Contracts\ManifestNodeSource;
use Schemastud\DataSchemas\Overlay\OverlayStack;
use Schemastud\DataSchemas\Overlay\SchemaOverlayApplier;

/**
 * Builds the node-manifest document for a profile (splicewire-editor issue 06):
 * the frozen JSON shape a client `assemblePMSchema` consumes to derive a
 * ProseMirror schema. Per node — name/description/group from `#[NodeType]`,
 * category/admitsChildCategories from the Block, and the attrs JSON Schema from
 * the {@see BlockJsonSchemaGenerator} with `id` always present.
 *
 * Category `$id`s are host vocabulary and may be URLs; ProseMirror content
 * expressions only allow word characters, so the manifest emits a stable PM-safe
 * slug per category ({@see self::categorySlug()}: last URL path segment,
 * `-` → `_`) in `category`/`admitsChildCategories`, preserving the raw `$id` as
 * an additive `categoryId` when it differs.
 *
 * The base prose vocabulary (p/h1..h6/lists + strong/em/code/link marks; each
 * node's name is its JSX tag, ADR-0228 d6) is NOT emitted per profile; it is the
 * canonical hand-authored manifest at
 * {@see self::baseManifestPath()}, vendored client-side.
 */
class ManifestBuilder
{
    /** The manifest format version this builder emits. */
    public const VERSION = 1;

    protected BlockJsonSchemaGenerator $generator;

    public function __construct(
        protected ManifestNodeSource $source,
        ?BlockJsonSchemaGenerator $generator = null,
    ) {
        $this->generator = $generator ?? new BlockJsonSchemaGenerator;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $profile): array
    {
        return [
            'profile' => $profile,
            'version' => self::VERSION,
            // ADR-0227 / ux-0227 d1 — whether this profile admits code (expression props, opaque nodes,
            // MDX expressions). A source opts out through DeclaresCodeAdmission; one that does not admits
            // code (true), so existing sources are unchanged. The save check reads this key.
            'x-admits-code' => $this->source instanceof DeclaresCodeAdmission
                ? $this->source->admitsCodeFor($profile)
                : true,
            'doc' => [
                'admitsChildCategories' => $this->categorySlugs($this->source->docAdmitsFor($profile)),
            ],
            'nodes' => array_map(
                fn (string $class): array => $this->node($class),
                array_values($this->source->nodeClassesFor($profile)),
            ),
            'marks' => array_values($this->source->marksFor($profile)),
        ];
    }

    /**
     * The canonical hand-authored base manifest (base prose vocabulary + marks).
     */
    public static function baseManifestPath(): string
    {
        return dirname(__DIR__, 2).'/resources/manifests/base.manifest.json';
    }

    /**
     * @return array<string, mixed>
     */
    public static function baseManifest(): array
    {
        return json_decode((string) file_get_contents(self::baseManifestPath()), true);
    }

    /**
     * @param  class-string<Block>  $class
     * @return array<string, mixed>
     */
    protected function node(string $class): array
    {
        $reflection = new ReflectionClass($class);

        $attrs = $reflection->getAttributes(NodeType::class);

        if (empty($attrs)) {
            throw new \LogicException($class.' must have a #[NodeType] attribute to be emitted in a manifest.');
        }

        $nodeType = $attrs[0]->newInstance();

        // category()/admitsChildCategories() are declarative instance methods; no
        // constructor args are needed to read them.
        /** @var Block $block */
        $block = $reflection->newInstanceWithoutConstructor();

        $node = ['name' => $nodeType->name];

        if ($nodeType->description !== null) {
            $node['description'] = $nodeType->description;
        }

        $category = $block->category();
        $slug = self::categorySlug($category);

        $node += [
            'group' => $nodeType->group,
            'category' => $slug,
        ];

        // Preserve the raw category $id additively when slugging changed it.
        if ($category !== null && $category !== $slug) {
            $node['categoryId'] = $category;
        }

        $node = $node + [
            'admitsChildCategories' => $this->categorySlugs($block->admitsChildCategories()),
            // Typed Blocks hold structured children or categories, never raw inline
            // text; the explicit content-expression escape hatch is base-set-only.
            'admitsText' => false,
            'contentExpression' => null,
            'attrsSchema' => $this->attrsSchema($reflection),
        ];

        return $this->applyOverrides($node, $reflection);
    }

    /**
     * ED-15 — emit `x-skin` (node-level) + `x-widget` (per-attr) **override-only**,
     * folded via DataOverlay's SchemaOverlayApplier (per DO-07 — one override
     * mechanism across both PHP packages, not a hand-rolled projector). When no
     * override is authored the node is returned unchanged, so the default case is
     * byte-for-byte identical and the client node-type-default resolution stands
     * alone. The manifest declares only the key; the client registry maps
     * key → component.
     *
     * @param  ReflectionClass<Block>  $reflection
     */
    protected function applyOverrides(array $node, ReflectionClass $reflection): array
    {
        $actions = [];

        $skin = $reflection->getAttributes(Skin::class);

        if (! empty($skin)) {
            $actions[] = ['target' => "$['x-skin']", 'override' => $skin[0]->newInstance()->name];
        }

        $properties = $node['attrsSchema']['properties'] ?? [];

        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();

            // Only overlay a property the attrsSchema actually emitted — never
            // fabricate one (create-absent would otherwise invent it).
            if (! array_key_exists($name, $properties)) {
                continue;
            }

            $widget = $property->getAttributes(Widget::class);

            if (empty($widget)) {
                continue;
            }

            $actions[] = [
                'target' => "$.attrsSchema.properties['".$name."']['x-widget']",
                'override' => $widget[0]->newInstance()->name,
            ];
        }

        if (empty($actions)) {
            return $node;
        }

        $overlay = new OverlayStack([['overlay' => '1.0.0', 'actions' => $actions]]);

        return (new SchemaOverlayApplier($node, $overlay))->apply();
    }

    /**
     * The PM-safe slug for a category `$id`: the last URL path segment with `-`
     * replaced by `_` (content expressions only allow word characters). Stable and
     * deterministic; a plain non-URL category slugs to itself.
     */
    public static function categorySlug(?string $category): ?string
    {
        if ($category === null) {
            return null;
        }

        // Take the last segment under EITHER separator. Category ids were absolute URIs
        // (`…/json-schemas/block-category/content-section`) until registry-kernel 58 D4 retired that
        // form for composition kinds in favour of dotted keys (`block-category.content-section`).
        // Splitting on `/` alone silently returned the WHOLE dotted key, so the slug became
        // `block_category.content_section` and every typed block failed validation with a 422 —
        // one test, in a package D4 never named, because the slug derivation lives here and not
        // beside the constants it consumes.
        $tail = (string) Str::afterLast(rtrim($category, '/.'), '/');

        return str_replace('-', '_', (string) Str::afterLast($tail, '.'));
    }

    /**
     * @param  list<string>|null  $categories
     * @return list<string>|null
     */
    protected function categorySlugs(?array $categories): ?array
    {
        if ($categories === null) {
            return null;
        }

        return array_values(array_map(
            fn (string $category): string => (string) self::categorySlug($category),
            $categories,
        ));
    }

    /**
     * The Block's attrs projected as a JSON Schema object, with `id` injected as
     * a nullable string when the Block declares no id property of its own.
     *
     * @param  ReflectionClass<Block>  $reflection
     * @return array<string, mixed>
     */
    protected function attrsSchema(ReflectionClass $reflection): array
    {
        $schema = $this->generator->generate($reflection);

        $properties = $schema['properties'] ?? [];

        if (! isset($properties['id'])) {
            $properties = ['id' => ['type' => ['string', 'null']]] + $properties;
        }

        $schema['properties'] = $properties;

        return $schema;
    }
}
