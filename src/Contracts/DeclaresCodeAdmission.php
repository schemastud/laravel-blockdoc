<?php

namespace Schemastud\Blockdoc\Contracts;

/**
 * An OPTIONAL declaration a {@see ManifestNodeSource} may add to say whether one of its profiles admits
 * code — expression props, opaque (verbatim-code) nodes, and MDX expressions (ADR-0227 / ux-0227 d1).
 *
 * A source that does not implement this is unchanged: its manifests carry `x-admits-code: true`, so the
 * save admits code as before. A hosted-site profile implements it and returns false, and the save check
 * reading the manifest refuses code there. Kept a separate opt-in interface so adding code-admission to
 * the grammar does not break the existing {@see ManifestNodeSource} implementers.
 */
interface DeclaresCodeAdmission
{
    /**
     * Whether the named profile admits code. Default behaviour, for a source that does not implement
     * this interface, is `true`.
     */
    public function admitsCodeFor(string $profile): bool;
}
