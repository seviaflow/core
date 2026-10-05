<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Resources\Fields\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

final class RichTextSanitizer
{
    /** @var array<string, true> */
    private const ALLOWED_ELEMENTS = [
        'a' => true,
        'br' => true,
        'em' => true,
        'h2' => true,
        'h3' => true,
        'li' => true,
        'ol' => true,
        'p' => true,
        'strong' => true,
        'ul' => true,
    ];

    /** @var array<string, true> */
    private const DANGEROUS_ELEMENTS = [
        'iframe' => true,
        'math' => true,
        'object' => true,
        'script' => true,
        'style' => true,
        'svg' => true,
        'template' => true,
    ];

    public function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrors = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"?><!DOCTYPE html><html><body><div id="seviaflow-rich-text-root">'
                .$html
                .'</div></body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }

        if ($loaded === false) {
            return '';
        }

        $roots = $document->getElementsByTagName('div');
        $root = $roots->item(0);
        if (! $root instanceof DOMElement) {
            return '';
        }

        $this->sanitizeChildren($root);

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return $this->isVisuallyEmpty($result) ? '' : trim($result);
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if (! $child instanceof DOMElement) {
                if ($child->nodeType === XML_COMMENT_NODE) {
                    $parent->removeChild($child);
                }

                continue;
            }

            $tag = strtolower($child->tagName);
            if (isset(self::DANGEROUS_ELEMENTS[$tag])) {
                $parent->removeChild($child);

                continue;
            }

            if (! isset(self::ALLOWED_ELEMENTS[$tag])) {
                $this->unwrap($parent, $child);

                continue;
            }

            $this->sanitizeAttributes($child, $tag);
            $this->sanitizeChildren($child);
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowedAttributes = $tag === 'a' ? ['href' => true] : [];
        $attributes = [];

        foreach ($element->attributes as $attribute) {
            $attributes[] = $attribute->name;
        }

        foreach ($attributes as $attribute) {
            if (! isset($allowedAttributes[strtolower($attribute)])) {
                $element->removeAttribute($attribute);
            }
        }

        if ($tag === 'a' && $element->hasAttribute('href') && ! $this->isSafeHref($element->getAttribute('href'))) {
            $element->removeAttribute('href');
        }
    }

    private function unwrap(DOMNode $parent, DOMElement $element): void
    {
        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private function isSafeHref(string $href): bool
    {
        $href = trim($href);
        if ($href === '' || preg_match('/[\x00-\x20\x7f]/', $href) === 1) {
            return false;
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));
        if ($scheme !== '') {
            return in_array($scheme, ['http', 'https', 'mailto'], true);
        }

        return str_starts_with($href, '#')
            || (str_starts_with($href, '/') && ! str_starts_with($href, '//'))
            || (! str_contains($href, ':') && ! str_starts_with($href, '\\'));
    }

    private function isVisuallyEmpty(string $html): bool
    {
        $withoutBreaks = preg_replace('/<br\s*\/?>/i', '', $html) ?? $html;
        $text = html_entity_decode(strip_tags($withoutBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xC2\xA0", ' ', $text);

        return trim($text) === '';
    }
}
