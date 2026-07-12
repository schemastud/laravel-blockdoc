<?php

namespace Schemastud\BlockSchema\Codegen;

/**
 * Compiles a node JSON Schema into a PHP source string for a named `Block` subclass.
 * The generated class carries `#[NodeType]` and `#[NodeAttr]` attributes so it is
 * indistinguishable from a hand-authored equivalent in the hydrator, schema generator,
 * and renderer — proving ADR-0024's DTO-as-universal-interface on-ramp.
 *
 * This is a codegen-to-cache tool: write the output to a file and require it. Never eval.
 */
class BlockClassGenerator
{
    /**
     * @param  string  $className  Simple class name (no namespace) for the generated class.
     * @param  string  $nodeType  The `type` discriminator string (e.g. "custom_card").
     * @param  array<string, mixed>  $schema  JSON Schema describing the node's properties.
     * @param  string|null  $namespace  Optional namespace for the generated class (default: global).
     */
    public function generate(
        string $className,
        string $nodeType,
        array $schema,
        ?string $namespace = null,
    ): string {
        $description = $schema['description'] ?? null;
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];

        // Exclude the `type` discriminator — it is not a constructor param.
        unset($properties['type']);

        $constructorParams = $this->buildConstructorParams($properties, $required);
        $nodeTypeArgs = $this->buildNodeTypeArgs($nodeType, $description);

        $namespaceLine = $namespace !== null ? "namespace {$namespace};\n\n" : '';
        $params = $constructorParams !== [] ? "\n".implode(",\n", $constructorParams)."\n    " : '';

        return <<<PHP
<?php

declare(strict_types=1);

{$namespaceLine}use Schemastud\BlockSchema\Attributes\NodeAttr;
use Schemastud\BlockSchema\Attributes\NodeType;
use Schemastud\BlockSchema\Blocks\Block;

#[NodeType({$nodeTypeArgs})]
class {$className} extends Block
{
    public function __construct({$params}) {}
}
PHP;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  list<string>  $required
     * @return list<string>
     */
    private function buildConstructorParams(array $properties, array $required): array
    {
        $params = [];

        foreach ($properties as $name => $prop) {
            $isRequired = in_array($name, $required, true);
            $phpType = $this->resolvePhpType($prop, $isRequired);
            $description = $prop['description'] ?? null;
            $default = $isRequired ? '' : ' = null';

            $nodeAttrArgs = $this->buildNodeAttrArgs($description, $isRequired);
            $params[] = "        #[NodeAttr({$nodeAttrArgs})]\n        public {$phpType} \${$name}{$default}";
        }

        return $params;
    }

    /** @param array<string, mixed> $prop */
    private function resolvePhpType(array $prop, bool $required): string
    {
        $type = $prop['type'] ?? 'string';

        // Nullable: ["string", "null"] or ["null", "string"]
        if (is_array($type)) {
            $nonNull = array_values(array_filter($type, fn ($t) => $t !== 'null'));
            $base = $this->jsonTypeToPhp($nonNull[0] ?? 'string');

            return '?'.$base;
        }

        $base = $this->jsonTypeToPhp($type);

        return $required ? $base : '?'.$base;
    }

    private function jsonTypeToPhp(string $jsonType): string
    {
        return match ($jsonType) {
            'integer' => 'int',
            'number' => 'float',
            'boolean' => 'bool',
            'array' => 'array',
            default => 'string',
        };
    }

    private function buildNodeTypeArgs(string $nodeType, ?string $description): string
    {
        $args = "'{$nodeType}'";

        if ($description !== null) {
            $escaped = addslashes($description);
            $args .= ", '{$escaped}'";
        }

        return $args.', group: \'embed\'';
    }

    private function buildNodeAttrArgs(?string $description, bool $required): string
    {
        $parts = [];

        if ($description !== null) {
            $escaped = addslashes($description);
            $parts[] = "description: '{$escaped}'";
        }

        if (! $required) {
            $parts[] = 'required: false';
            $parts[] = 'default: null';
        }

        return implode(', ', $parts);
    }
}
