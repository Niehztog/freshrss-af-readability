<?php

namespace fivefilters\Readability\Nodes\DOM;

use fivefilters\Readability\Nodes\NodeTrait;

/**
 * @method getAttribute($attribute)
 * @method hasAttribute($attribute)
 */
class DOMNode extends \DOMNode
{
    use NodeTrait;
}
