<?php

namespace Schemastud\Blockdoc\Blocks;

use Illuminate\Support\Str;
use Schemastud\Blockdoc\Attributes\NodeType;
use Schemastud\Blockdoc\Contracts\Node;
use Schemastud\Blockdoc\Exceptions\BlockContainmentException;
use Spatie\LaravelData\Data;

/**
 * A ProseMirror Node implemented as a Spatie Data object. The Data payload holds
 * the node's attributes; `id` and child `content` are managed by this base class
 * (kept out of the payload so Spatie never tries to hydrate polymorphic children).
 */
abstract class Block extends Data implements Node
{
    protected ?string $id = null;

    /** @var list<Node> */
    protected array $children = [];

    public function type(): string
    {
        $attrs = (new \ReflectionClass(static::class))->getAttributes(NodeType::class);

        if (empty($attrs)) {
            throw new \LogicException(static::class.' must have a #[NodeType] attribute.');
        }

        return $attrs[0]->newInstance()->name;
    }

    /**
     * The UUIDv7 node id, stamped lazily on first access and stable thereafter.
     */
    public function id(): string
    {
        return $this->id ??= (string) Str::uuid7();
    }

    /**
     * Set the node id (used by the DocumentHydrator to preserve an incoming id).
     * Passing null leaves it unstamped so it will be generated on next access.
     */
    public function withId(?string $id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * The category this block belongs to (an opaque `$id` string). Null means the
     * block declares no category — it can only be placed in an unconstrained
     * container. The category vocabulary is the host's; the block layer treats ids
     * as opaque, exactly like the kernel's admitted-category SlotSchema.
     */
    public function category(): ?string
    {
        return null;
    }

    /**
     * The child categories this block admits, or null when unconstrained (any
     * child, including raw prose). A container admits *by category*; a child whose
     * {@see category()} is not in this set is rejected on write — the block layer's
     * realization of the category-admission seam (replacing the retired, inert
     * `#[NodeType]` content expression).
     *
     * @return list<string>|null
     */
    public function admitsChildCategories(): ?array
    {
        return null;
    }

    /**
     * Named component slots (ux-0231 d9). The default slot remains children,
     * governed by admitsChildCategories(); named slots have their own admission.
     *
     * @return list<array{name: string, admitsChildCategories: list<string>|null}>
     */
    public function slots(): array
    {
        return [];
    }

    /**
     * @param  list<Node>  $children
     *
     * @throws BlockContainmentException when a child's category is not admitted
     */
    public function withContent(array $children): static
    {
        $admitted = $this->admitsChildCategories();

        if ($admitted !== null) {
            foreach ($children as $child) {
                $childCategory = $child instanceof self ? $child->category() : null;

                if (! in_array($childCategory, $admitted, true)) {
                    throw new BlockContainmentException(sprintf(
                        '%s does not admit a child of category [%s]; admitted: [%s].',
                        static::class,
                        $childCategory ?? '(none)',
                        implode(', ', $admitted),
                    ));
                }
            }
        }

        $this->children = $children;

        return $this;
    }

    /** @return array<string, mixed> */
    public function attrs(): array
    {
        return ['id' => $this->id(), ...parent::toArray()];
    }

    /** @return list<Node> */
    public function content(): array
    {
        return $this->children;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $node = [
            'type' => $this->type(),
            'attrs' => $this->attrs(),
        ];

        if ($this->children !== []) {
            $node['content'] = array_map(fn (Node $child): array => $child->toArray(), $this->children);
        }

        return $node;
    }
}
