<?php

namespace Schemastud\Blockdoc;

use Rushing\Popcorn\Registries\Authorizer;
use Rushing\Popcorn\Registries\BasicRegistry;
use Rushing\Popcorn\Registries\Gated;
use Rushing\Popcorn\Registries\IsRegistry;
use Rushing\Popcorn\Registries\Key;
use Rushing\Popcorn\Registries\OnKeyDuplicate;
use Rushing\Popcorn\Registries\Registry;
use Rushing\Popcorn\Registries\RegistryKey;
use Schemastud\Blockdoc\Contracts\DocumentTransform;

/**
 * Ordered registry of {@see DocumentTransform} class-strings. Structural transforms are
 * registered by the package ServiceProvider; grounding-driven transforms are appended by
 * the consuming app (ADR-0021 registry/config seam).
 *
 * ## Registration order IS the semantics, and it is now the kernel's guarantee
 *
 * The chain used to be a plain `list<class-string>` whose order was a PHP array's insertion order,
 * trusted implicitly. It is now {@see Registry::matches()}' documented registration-order guarantee
 * (registry-kernel ticket 08 D4) — the same property, said out loud by the contract that provides it.
 *
 * `onKeyDuplicate` stays {@see OnKeyDuplicate::Admit} and that is load-bearing here, not incidental: under
 * `Supersede` a second registration of the same transform would MOVE the entry to the end of the chain,
 * where appending to an array kept both in place. For a pipeline that is a visible defect. Admit keeps
 * two live entries under one key, exactly as the old array kept two elements.
 */
#[IsRegistry(
    root: 'blockdoc.transforms',
    entryType: 'class-string<'.DocumentTransform::class.'>',
    onKeyDuplicate: OnKeyDuplicate::Admit,
    description: 'Document transforms applied in registration order. transform() passes each result to the next transform. Structural transforms precede host and grounding transforms, as specified in ADR-0021.',
    order: 51,
)]
class TransformPipeline implements Gated, Registry
{
    /** @var BasicRegistry<class-string<DocumentTransform>> */
    private BasicRegistry $entries;

    public function __construct()
    {
        $this->entries = BasicRegistry::for($this);
    }

    /**
     * Register a transform.
     *
     * WIDENED from the contract rather than shadowing it — contravariance, so the historical
     * one-argument call `register(SomeTransform::class)` keeps working and keys itself off the class
     * name, while a caller that wants to name the key may still pass one.
     *
     * @param  class-string<DocumentTransform>|RegistryKey|string  $key
     * @param  class-string<DocumentTransform>|null  $entry
     */
    public function register(RegistryKey|string $key, mixed $entry = null, ?string $by = null, ?string $ability = null): static
    {
        if ($entry === null && is_string($key)) {
            $entry = $key;
            $key = $this->keyFor($key);
        }

        $this->entries->register($key, $entry, $by, $ability);

        return $this;
    }

    /**
     * The registered transform class-strings, in registration order — the chain, as the caller spelled
     * it. Rebuilt from the store rather than kept beside it, which is the whole migration.
     *
     * `matches()` on the declared root rather than a `relativeKeys()` walk: this registry admits
     * duplicates by design, and a per-key `resolve()` would throw {@see
     * \Rushing\Popcorn\Registries\Exceptions\AmbiguousRegistryMatch} the moment one transform is
     * registered twice — which the old array happily allowed.
     *
     * @return list<class-string<DocumentTransform>>
     */
    public function transforms(): array
    {
        return $this->entries->matches($this->entries->root());
    }

    /**
     * The registered transform ids, as callers spelled them — the relative half of {@see keys()},
     * because keys go relative in and absolute out (ticket 20 D2).
     *
     * @return string[]
     */
    public function ids(): array
    {
        return $this->entries->relativeKeys();
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $context  Runtime data forwarded to each transform
     * @return array<string, mixed>
     */
    public function transform(array $input, array $context = []): array
    {
        foreach ($this->transforms() as $class) {
            $input = app($class)->transform($input, $context);
        }

        return $input;
    }

    public function has(RegistryKey|string $key): bool
    {
        return $this->entries->has($key);
    }

    public function resolve(RegistryKey|string $key): mixed
    {
        return $this->entries->resolve($key);
    }

    public function tryResolve(RegistryKey|string $key): mixed
    {
        return $this->entries->tryResolve($key);
    }

    public function matches(RegistryKey|string $key): array
    {
        return $this->entries->matches($key);
    }

    public function keys(): array
    {
        return $this->entries->keys();
    }

    public function unfiltered(): Registry
    {
        return $this->entries->unfiltered();
    }

    public function authorizeWith(?Authorizer $authorizer): static
    {
        $this->entries->authorizeWith($authorizer);

        return $this;
    }

    /**
     * The key a self-keying registration takes: {@see Key::fromClass()}, the kernel's sanctioned
     * derivation for class-string entries.
     *
     * An ANONYMOUS transform class has no legal name to derive from — PHP spells it
     * `class@anonymous\0/path/to/file.php:12$0`, whose `@`, `/`, `.` and `:` fail the key charset
     * outright — and registering one is a live idiom in this package's own suite. It gets a stable
     * digest key instead. Nothing addresses a transform by name; the chain composes in registration
     * order, so the key only has to be legal and stable.
     */
    private function keyFor(string $class): RegistryKey
    {
        if (str_contains($class, '@')) {
            return Key::parse('anonymous-'.substr(md5($class), 0, 12));
        }

        return Key::fromClass($class);
    }
}
