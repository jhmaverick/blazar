<?php

/*
 * This file is part of Blazar Framework.
 *
 * (c) João Henrique <joao_henriquee@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Blazar\Component\SelectorDOM;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;

/**
 * Persistent object for selecting elements.
 *
 * Forked from Selector.inc @link https://github.com/tj/php-selector
 * Selector.inc - Copyright (c) 2008-2009 TJ Holowaychuk <tj@vision-media.ca> MIT Licensed
 *
 * <code>
 *   $dom = new SelectorDOM($html);
 *   $links = $dom->select('a');
 *   $list_links = $dom->select('ul li a');
 * </code>
 */
class SelectorDOM {
    private $xpath;

    /**
     * SelectorDOM constructor.
     *
     * @param string|DOMDocument $data Um objeto DOMDocument ou uma string contendo um html.
     */
    public function __construct($data) {
        $data = mb_convert_encoding($data, 'HTML-ENTITIES', 'UTF-8');

        if ($data instanceof DOMDocument) {
            $this->xpath = new DOMXpath($data);
        } else {
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = true;
            $dom->formatOutput = false;

            @$dom->loadHTML($data);
            $this->xpath = new DOMXpath($dom);
        }
    }

    /**
     * Select elements from $html using the css $selector.
     * When $as_array is true elements and their children will
     * be converted to array's containing the following keys (defaults to true):.
     *
     *  - name : element name
     *  - text : element text
     *  - children : array of children elements
     *  - attributes : attributes array
     *
     * @param string $selector
     * @param bool $as_array
     *
     * @return array|DOMNodeList Otherwise regular DOMElement's will be returned.
     */
    public function select(string $selector, bool $as_array = true) {
        $elements = $this->xpath->evaluate($this->selectorToXpath($selector));

        return $as_array ? $this->elementsToArray($elements) : $elements;
    }

    /**
     * Convert $selector into an XPath string.
     *
     * @param string $selector
     *
     * @return string|string[]|null
     */
    public function selectorToXpath(string $selector): string {
        // remove spaces around operators
        $selector = preg_replace('/\s*>\s*/', '>', $selector);
        $selector = preg_replace('/\s*~\s*/', '~', $selector);
        $selector = preg_replace('/\s*\+\s*/', '+', $selector);
        $selector = preg_replace('/\s*,\s*/', ',', $selector);
        $selectors = preg_split('/\s+(?![^\[]+\])/', $selector);

        foreach ($selectors as $i => $selector) {
            $selector = preg_replace('/,/', '|descendant-or-self::', $selector);
            // input:checked, :disabled, etc.
            $selector = preg_replace('/(.+)?:(checked|disabled|required|autofocus)/', '\1[@\2="\2"]', $selector);
            // input:autocomplete, :autocomplete
            $selector = preg_replace('/(.+)?:(autocomplete)/', '\1[@\2="on"]', $selector);
            // input:button, input:submit, etc.
            $selector = preg_replace('/:(text|password|checkbox|radio|button|submit|reset|file|hidden|image|datetime|datetime-local|date|month|time|week|number|range|email|url|search|tel|color)/', 'input[@type="\1"]', $selector);
            // foo[id]
            $selector = preg_replace('/(\w+)\[([_\w-]+[_\w\d-]*)\]/', '\1[@\2]', $selector);
            // [id]
            $selector = preg_replace('/\[([_\w-]+[_\w\d-]*)\]/', '*[@\1]', $selector);
            // foo[id=foo]
            $selector = preg_replace('/\[([_\w-]+[_\w\d-]*)=[\'"]?(.*?)[\'"]?\]/', '[@\1="\2"]', $selector);
            // [id=foo]
            $selector = preg_replace('/^\[/', '*[', $selector);
            // div#foo
            $selector = preg_replace('/([_\w-]+[_\w\d-]*)\#([_\w-]+[_\w\d-]*)/', '\1[@id="\2"]', $selector);
            // #foo
            $selector = preg_replace('/\#([_\w-]+[_\w\d-]*)/', '*[@id="\1"]', $selector);
            // div.foo
            $selector = preg_replace('/([_\w-]+[_\w\d-]*)\.([_\w-]+[_\w\d-]*)/', '\1[contains(concat(" ",@class," ")," \2 ")]', $selector);
            // .foo
            $selector = preg_replace('/\.([_\w-]+[_\w\d-]*)/', '*[contains(concat(" ",@class," ")," \1 ")]', $selector);
            // div:first-child
            $selector = preg_replace('/([_\w-]+[_\w\d-]*):first-child/', '*/\1[position()=1]', $selector);
            // div:last-child
            $selector = preg_replace('/([_\w-]+[_\w\d-]*):last-child/', '*/\1[position()=last()]', $selector);
            // :first-child
            $selector = str_replace(':first-child', '*/*[position()=1]', $selector);
            // :last-child
            $selector = str_replace(':last-child', '*/*[position()=last()]', $selector);
            // :nth-last-child
            $selector = preg_replace('/:nth-last-child\((\d+)\)/', '[position()=(last() - (\1 - 1))]', $selector);
            // div:nth-child
            $selector = preg_replace('/([_\w-]+[_\w\d-]*):nth-child\((\d+)\)/', '*/*[position()=\2 and self::\1]', $selector);
            // :nth-child
            $selector = preg_replace('/:nth-child\((\d+)\)/', '*/*[position()=\1]', $selector);
            // :contains(Foo)
            $selector = preg_replace('/([_\w-]+[_\w\d-]*):contains\((.*?)\)/', '\1[contains(string(.),"\2")]', $selector);
            // >
            $selector = preg_replace('/>/', '/', $selector);
            // ~
            $selector = preg_replace('/~/', '/following-sibling::', $selector);
            // +
            $selector = preg_replace('/\+([_\w-]+[_\w\d-]*)/', '/following-sibling::\1[position()=1]', $selector);
            $selector = str_replace(']*', ']', $selector);
            $selector = str_replace(']/*', ']', $selector);

            $selectors[$i] = $selector;
        }

        $selector = implode('/descendant::', $selectors);
        $selector = 'descendant-or-self::' . $selector;
        // :scope
        $selector = preg_replace('/(((\|)?descendant-or-self::):scope)/', '.\3', $selector);
        // $element
        $sub_selectors = explode(',', $selector);

        foreach ($sub_selectors as $key => $sub_selector) {
            $parts = explode('$', $sub_selector);
            $sub_selector = array_shift($parts);

            if (count($parts) && preg_match_all('/((?:[^\/]*\/?\/?)|$)/', $parts[0], $matches)) {
                $results = $matches[0];
                $results[] = str_repeat('/..', count($results) - 2);
                $sub_selector .= implode('', $results);
            }

            $sub_selectors[$key] = $sub_selector;
        }

        $selector = implode(',', $sub_selectors);

        return $selector;
    }

    /**
     * Convert $elements to an array.
     *
     * @param DOMNodeList $elements
     *
     * @return array
     */
    public function elementsToArray(DOMNodeList $elements): array {
        $array = [];

        for ($i = 0, $length = $elements->length; $i < $length; ++$i) {
            if ($elements->item($i)->nodeType == XML_ELEMENT_NODE) {
                array_push($array, $this->elementToArray($elements->item($i)));
            }
        }

        return $array;
    }

    /**
     * Convert $element to an array.
     *
     * @param DOMElement $element
     *
     * @return array
     */
    public function elementToArray(DOMElement $element): array {
        $array = [
            'name' => $element->nodeName,
            'attributes' => [],
            'text' => $element->textContent,
            'children' => $this->elementsToArray($element->childNodes),
        ];

        if ($element->attributes->length) {
            foreach ($element->attributes as $key => $attr) {
                $array['attributes'][$key] = $attr->value;
            }
        }

        return $array;
    }

    /**
     * Convert $element to a string html.
     *
     * @param DOMElement $element
     * @param bool $inner_html
     *
     * @return string
     */
    public function elementToHtml(DOMElement $element, bool $inner_html = true): string {
        if ($inner_html) {
            $innerHTML = [];
            $children = $element->childNodes;

            foreach ($children as $child) {
                $innerHTML[] = $child->ownerDocument->saveHTML($child);
            }

            $html = implode('', $innerHTML);
        } else {
            $html = $element->ownerDocument->saveHTML($element);
        }

        $html = str_replace('&gt;', '>', $html);
        // TODO colocar para substituir apenas dentro do href
        $html = str_replace('%7B', '{', $html);
        $html = str_replace('%7D', '}', $html);

        return $html;
    }
}