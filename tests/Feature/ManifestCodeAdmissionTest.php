<?php

use Schemastud\Blockdoc\Attributes\NodeType;
use Schemastud\Blockdoc\Blocks\Block;
use Schemastud\Blockdoc\Contracts\DeclaresCodeAdmission;
use Schemastud\Blockdoc\Contracts\ManifestNodeSource;
use Schemastud\Blockdoc\Schema\ManifestBuilder;

/**
 * ADR-0227 / ux-0227 d1 — a manifest profile carries `x-admits-code`: whether a save against it admits
 * code (expression props, opaque nodes, MDX expressions). A source declares it through the optional
 * {@see DeclaresCodeAdmission}; a source that does not is unchanged and admits code (default true), so
 * existing ManifestNodeSource implementers keep working.
 */
#[NodeType('admission_block', 'A code-admission fixture')]
class AdmissionBlockFixture extends Block
{
    public function category(): ?string
    {
        return 'section';
    }
}

class PlainAdmissionSource implements ManifestNodeSource
{
    public function profiles(): array
    {
        return ['fixture'];
    }

    public function nodeClassesFor(string $profile): array
    {
        return [AdmissionBlockFixture::class];
    }

    public function docAdmitsFor(string $profile): ?array
    {
        return ['section'];
    }

    public function marksFor(string $profile): array
    {
        return [];
    }
}

it('defaults x-admits-code to true when the source does not declare it (ux-0227 d1)', function () {
    expect((new ManifestBuilder(new PlainAdmissionSource))->build('fixture')['x-admits-code'])->toBeTrue();
});

it('emits x-admits-code false when the source declares a profile refuses code (ux-0227 d1)', function () {
    $source = new class extends PlainAdmissionSource implements DeclaresCodeAdmission
    {
        public function admitsCodeFor(string $profile): bool
        {
            return false;
        }
    };

    expect((new ManifestBuilder($source))->build('fixture')['x-admits-code'])->toBeFalse();
});
