<?php

use Schemastud\Blockdoc\Attributes\NodeType;
use Schemastud\Blockdoc\Blocks\Block;
use Schemastud\Blockdoc\Contracts\ManifestNodeSource;
use Schemastud\Blockdoc\Schema\ManifestBuilder;

#[NodeType('Card')]
class SlottedCardFixture extends Block
{
    public function slots(): array
    {
        return [['name' => 'media', 'admitsChildCategories' => ['image']], ['name' => 'footer', 'admitsChildCategories' => []]];
    }
}

class ManifestSlotsTest extends Schemastud\Blockdoc\Tests\TestCase
{
    public function test_emits_named_slot_categories_without_changing_default_child_admission(): void
    {
        $source = new class implements ManifestNodeSource
        {
            public function profiles(): array
            {
                return ['test'];
            }

            public function nodeClassesFor(string $profile): array
            {
                return [SlottedCardFixture::class];
            }

            public function docAdmitsFor(string $profile): ?array
            {
                return null;
            }

            public function marksFor(string $profile): array
            {
                return [];
            }
        };
        $node = (new ManifestBuilder($source))->build('test')['nodes'][0];
        $this->assertSame([
            ['name' => 'media', 'admitsChildCategories' => ['image']],
            ['name' => 'footer', 'admitsChildCategories' => []],
        ], $node['slots'] ?? null);
        $this->assertNull($node['admitsChildCategories']);
    }
}
