
## Binding addressing (shell convention)

Evidence/provenance bindings into block documents live in the HOST's SQL graph — the
authoritative side — and address into documents as:

```json
{ "@id": "<field pointer>", "x-sw-node": "<uuid>", "x-sw-span": "<uuid>" }
```

`@id` (the field pointer, e.g. `slots.bodyDoc`) stays base-owned (laravel-json-reference
doctrine); `x-sw-node` is the block node's `id` attr (UUIDv7, stamped by `Block::id()` or the
editor); the optional `x-sw-span` is an `annotation` mark id (the base manifest's annotation
mark, `excludes: ""`). Node/mark attrs are only a **location index** — bindings never store
payloads in the document, and a binding whose address vanishes is *flagged dangling* by the
host's commit-time reconciliation, never deleted. Every vertical shares this convention.

## Registries

This package declares two registries. [The blockdoc cluster](docs/registries/blockdoc.mdx) page
shows how they fit together.

- [`blockdoc.node-types`](docs/registries/blockdoc.node-types.mdx): the node types your application
  hydrates into typed `Block` classes.
- [`blockdoc.transforms`](docs/registries/blockdoc.transforms.mdx): the ordered transforms that tidy
  document input before it is built into blocks. The package registers its three built-in
  transforms here.
