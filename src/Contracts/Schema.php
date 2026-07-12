<?php

namespace Schemastud\Blockdoc\Contracts;

use Schemastud\Blockdoc\Blocks\Block;

/**
 * The node-type registry. The app populates it; the DocumentHydrator reads it.
 */
interface Schema
{
    /**
     * @param  class-string<Block>  $class
     */
    public function register(string $type, string $class): void;

    /**
     * Register a Block class by reading its #[NodeType] attribute name.
     *
     * @param  class-string<Block>  $class
     */
    public function registerClass(string $class): void;

    public function has(string $type): bool;

    /**
     * @return class-string<Block>
     */
    public function resolve(string $type): string;
}
