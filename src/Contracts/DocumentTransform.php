<?php

namespace Schemastud\Blockdoc\Contracts;

interface DocumentTransform
{
    /**
     * @param  array<string, mixed>  $input  BlockDocumentBuilder input shape
     * @param  array<string, mixed>  $context  Runtime grounding data passed by the caller
     * @return array<string, mixed>
     */
    public function transform(array $input, array $context = []): array;
}
