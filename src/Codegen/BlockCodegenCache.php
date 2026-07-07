<?php

namespace Rushing\BlockSchema\Codegen;

/**
 * Writes generated Block class PHP code to a cache directory and loads it.
 * Never uses eval — always writes a real .php file and require_once's it.
 * The on-ramp for ADR-0024: a foreign schema becomes a first-class node without
 * a hand-authored PHP class.
 */
class BlockCodegenCache
{
    public function __construct(private string $cacheDir) {}

    /**
     * Write the generated PHP code to cache (if not already present) and load the class.
     * Returns the fully-qualified class name (FQCN) ready for use.
     *
     * @param  string  $fqcn  Fully-qualified class name the code defines.
     * @param  string  $nodeType  Node type slug — used to derive the cache filename.
     * @param  string  $phpCode  PHP source produced by BlockClassGenerator::generate().
     * @return string The FQCN, unchanged, for fluent use.
     */
    public function compile(string $fqcn, string $nodeType, string $phpCode): string
    {
        if (! class_exists($fqcn)) {
            $path = $this->pathFor($nodeType);
            file_put_contents($path, $phpCode);
            require_once $path;
        }

        return $fqcn;
    }

    /**
     * Determine whether a cached class file exists for this node type.
     */
    public function has(string $nodeType): bool
    {
        return file_exists($this->pathFor($nodeType));
    }

    /**
     * Delete the cached class file for this node type (useful in tests / CI).
     */
    public function forget(string $nodeType): void
    {
        $path = $this->pathFor($nodeType);

        if (file_exists($path)) {
            unlink($path);
        }
    }

    private function pathFor(string $nodeType): string
    {
        $slug = preg_replace('/[^a-z0-9_]/', '_', strtolower($nodeType));

        return $this->cacheDir.DIRECTORY_SEPARATOR."block__{$slug}.php";
    }
}
