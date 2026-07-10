<?php

namespace Rushing\BlockSchema\Schema;

use ReflectionClass;
use Rushing\BlockSchema\Attributes\NodeType;
use Rushing\BlockSchema\Blocks\Block;
use Rushing\BlockSchema\Contracts\ManifestNodeSource;

/**
 * Builds the node-manifest document for a profile (splicewire-editor issue 06):
 * the frozen JSON shape a client `assemblePMSchema` consumes to derive a
 * ProseMirror schema. Per node — name/description/group from `#[NodeType]`,
 * category/admitsChildCategories from the Block, and the attrs JSON Schema from
 * the {@see BlockJsonSchemaGenerator} with `id` always present.
 *
 * The base prose vocabulary (paragraph/heading/lists + strong/em/code/link marks)
 * is NOT emitted per profile; it is the canonical hand-authored manifest at
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
            'doc' => [
                'admitsChildCategories' => $this->source->docAdmitsFor($profile),
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

        return $node + [
            'group' => $nodeType->group,
            'category' => $block->category(),
            'admitsChildCategories' => $block->admitsChildCategories(),
            // Typed Blocks hold structured children or categories, never raw inline
            // text; the explicit content-expression escape hatch is base-set-only.
            'admitsText' => false,
            'contentExpression' => null,
            'attrsSchema' => $this->attrsSchema($reflection),
        ];
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
