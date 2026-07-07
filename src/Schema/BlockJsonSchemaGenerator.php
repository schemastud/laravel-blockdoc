<?php

namespace Rushing\BlockSchema\Schema;

use ReflectionClass;
use ReflectionProperty;
use Rushing\BlockSchema\Attributes\NodeAttr;
use Rushing\BlockSchema\Attributes\NodeType;
use Rushing\BlockSchema\Blocks\Block;
use Rushing\LaravelDataSchemas\Generators\JsonSchemaGenerator;

/**
 * A laravel-data-schemas generator that understands the block-schema model:
 * it bridges `#[NodeType]` (class description) and `#[NodeAttr]` (property
 * description / example / required) into the emitted JSON Schema, so a Block's
 * native authoring metadata drives LLM structured-output schemas.
 *
 * Register it ahead of the default generator in `config/data-schemas.php`; its
 * `canGenerate()` only claims Block subclasses, leaving plain Data to the default.
 *
 * @see JsonSchemaGenerator
 */
class BlockJsonSchemaGenerator extends JsonSchemaGenerator
{
    public function canGenerate(ReflectionClass $class): bool
    {
        return $class->isSubclassOf(Block::class);
    }

    protected function getClassDescription(ReflectionClass $class): ?string
    {
        $attrs = $class->getAttributes(NodeType::class);

        if (! empty($attrs) && ($description = $attrs[0]->newInstance()->description) !== null) {
            return $description;
        }

        return parent::getClassDescription($class);
    }

    protected function generatePropertySchema(ReflectionProperty $property): array
    {
        $schema = parent::generatePropertySchema($property);

        if (! $nodeAttr = $this->nodeAttr($property)) {
            return $schema;
        }

        if (! isset($schema['description']) && $nodeAttr->description !== null) {
            $schema['description'] = $nodeAttr->description;
        }

        // An explicit NodeAttr example wins over the baseline the parent infers.
        if ($nodeAttr->example !== null) {
            $schema['examples'] = [$nodeAttr->example];
        }

        return $schema;
    }

    protected function isRequired(ReflectionProperty $property): bool
    {
        if (! $nodeAttr = $this->nodeAttr($property)) {
            return parent::isRequired($property);
        }

        // A property with a PHP default is never required, regardless of NodeAttr.
        if ($property->hasDefaultValue()) {
            return false;
        }

        return $nodeAttr->required;
    }

    private function nodeAttr(ReflectionProperty $property): ?NodeAttr
    {
        $attrs = $property->getAttributes(NodeAttr::class);

        return empty($attrs) ? null : $attrs[0]->newInstance();
    }
}
