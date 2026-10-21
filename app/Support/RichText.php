<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

class RichText
{
    /**
     * @var list<string>
     */
    private const ALLOWED_TAGS = ['p', 'br', 'h1', 'h2', 'h3', 'strong', 'b', 'em', 'i', 'u', 'span'];

    /**
     * @var list<string>
     */
    private const ALLOWED_ALIGNMENTS = ['left', 'right', 'center', 'justify'];

    public static function sanitize(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $wrapped = '<div id="rich-text-root">'.$html.'</div>';

        $document = new DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);

        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
        }

        $root = $document->getElementById('rich-text-root');

        if ($root === null) {
            return '';
        }

        self::sanitizeNode($root);

        $output = '';

        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    public static function toPlainText(?string $html): string
    {
        $plain = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $plain) ?? '');
    }

    public static function isBlank(?string $html): bool
    {
        return self::toPlainText($html) === '';
    }

    public static function fromPlain(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        return '<p>'.e($text).'</p>';
    }

    private static function sanitizeNode(DOMNode $node): void
    {
        if (! $node->hasChildNodes()) {
            return;
        }

        /** @var list<DOMNode> $children */
        $children = [];

        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                continue;
            }

            if ($child->nodeType !== XML_ELEMENT_NODE || ! $child instanceof DOMElement) {
                $node->removeChild($child);

                continue;
            }

            $tag = strtolower($child->tagName);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }

                $node->removeChild($child);

                continue;
            }

            self::sanitizeAttributes($child);
            self::sanitizeNode($child);
        }
    }

    private static function sanitizeAttributes(DOMElement $element): void
    {
        /** @var list<string> $attributeNames */
        $attributeNames = [];

        if ($element->hasAttributes()) {
            foreach ($element->attributes as $attribute) {
                $attributeNames[] = $attribute->name;
            }
        }

        foreach ($attributeNames as $name) {
            if (strtolower($name) !== 'style') {
                $element->removeAttribute($name);

                continue;
            }

            $alignment = self::extractTextAlign((string) $element->getAttribute('style'));

            if ($alignment === null) {
                $element->removeAttribute('style');

                continue;
            }

            $element->setAttribute('style', 'text-align: '.$alignment.';');
        }
    }

    private static function extractTextAlign(string $style): ?string
    {
        if (preg_match('/text-align\s*:\s*(left|right|center|justify)\s*;?/i', $style, $matches) !== 1) {
            return null;
        }

        $alignment = strtolower($matches[1]);

        return in_array($alignment, self::ALLOWED_ALIGNMENTS, true) ? $alignment : null;
    }
}
