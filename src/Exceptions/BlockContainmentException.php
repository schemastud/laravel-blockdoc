<?php

namespace Schemastud\Blockdoc\Exceptions;

use RuntimeException;

/**
 * Thrown when a child Node is placed into a container whose admitted child
 * categories do not include the child's category — the block layer's expression
 * of the category-admission seam (a container admits *by category*; a member
 * declares its category; an illegal child is rejected on write).
 */
class BlockContainmentException extends RuntimeException {}
