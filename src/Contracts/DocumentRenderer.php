<?php

namespace Rushing\BlockSchema\Contracts;

/**
 * The render seam — a bare interface, not a format-keyed registry. Each output
 * format (HTML, AMP, plain text) is a separate implementation in the app.
 */
interface DocumentRenderer
{
    public function render(Document $document): mixed;
}
