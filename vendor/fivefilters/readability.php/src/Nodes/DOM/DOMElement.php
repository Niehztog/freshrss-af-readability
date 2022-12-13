<?php

namespace fivefilters\Readability\Nodes\DOM;

use fivefilters\Readability\Nodes\NodeTrait;

class DOMElement extends \DOMElement
{
    use NodeTrait;

    /**
     * Returns the child elements of this element.
     * 
     * To get all child nodes, including non-element nodes like text and comment nodes, use childNodes.
     *
     * @return DOMNodeList
     */
    public function children()
    {
        $newList = new DOMNodeList();
        foreach ($this->childNodes as $node) {
            if ($node->nodeType === XML_ELEMENT_NODE) {
                $newList->add($node);
            }
        }
        return $newList;
    }

    /**
     * Returns the Element immediately prior to the specified one in its parent's children list, or null if the specified element is the first one in the list.
     *
     * @see https://wiki.php.net/rfc/dom_living_standard_api
     * @return DOMElement|null
     */
    public function previousElementSibling()
    {
        $previous = $this->previousSibling;
        while ($previous) {
            if ($previous->nodeType === XML_ELEMENT_NODE) {
                return $previous;
            }
            $previous = $previous->previousSibling;
        }
        return null;
    }
}
