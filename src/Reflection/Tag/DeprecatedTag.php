<?php

namespace Hahadu\Reflector\Reflection\Tag;

use Hahadu\Reflector\Reflection;
use Hahadu\Reflector\Reflection\Location;
use Hahadu\Reflector\Reflection\Tag\VersionTag;

/**
 * Reflection class for a @deprecated tag in a Docblock.
 *
 */
class DeprecatedTag extends VersionTag
{
    public function __construct(string $name, string $content, Reflection $docblock = null, Location $location = null)
    {
        parent::__construct($name, $content, $docblock, $location);
    }
}
