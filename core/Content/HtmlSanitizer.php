<?php

declare(strict_types=1);

namespace Core\Content;

use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;

final class HtmlSanitizer
{
    private const MAX_BYTES = 2_000_000;

    /** @var array<string, true> */
    private const INLINE_TAGS = [
        'span' => true,
        'strong' => true,
        'b' => true,
        'em' => true,
        'i' => true,
        'mark' => true,
        'small' => true,
        'br' => true,
        'sup' => true,
        'sub' => true,
    ];

    /** @var array<string, true> */
    private const RICH_TAGS = [
        'p' => true,
        'div' => true,
        'section' => true,
        'article' => true,
        'h2' => true,
        'h3' => true,
        'h4' => true,
        'h5' => true,
        'h6' => true,
        'ul' => true,
        'ol' => true,
        'li' => true,
        'blockquote' => true,
        'a' => true,
        'strong' => true,
        'b' => true,
        'em' => true,
        'i' => true,
        'u' => true,
        's' => true,
        'span' => true,
        'mark' => true,
        'small' => true,
        'br' => true,
        'hr' => true,
        'code' => true,
        'pre' => true,
        'table' => true,
        'thead' => true,
        'tbody' => true,
        'tfoot' => true,
        'tr' => true,
        'th' => true,
        'td' => true,
        'figure' => true,
        'figcaption' => true,
        'img' => true,
        'time' => true,
        'sup' => true,
        'sub' => true,
    ];

    /** @var array<string, true> */
    private const DROP_WITH_CONTENT = [
        'script' => true,
        'style' => true,
        'iframe' => true,
        'object' => true,
        'embed' => true,
        'form' => true,
        'input' => true,
        'button' => true,
        'textarea' => true,
        'select' => true,
        'option' => true,
        'link' => true,
        'meta' => true,
        'base' => true,
        'svg' => true,
        'math' => true,
    ];

    public function inline(string $html): string
    {
        return $this->sanitize($html, self::INLINE_TAGS);
    }

    public function rich(string $html): string
    {
        return $this->sanitize($html, self::RICH_TAGS);
    }

    /** @param array<string, true> $allowedTags */
    private function sanitize(string $html, array $allowedTags): string
    {
        if ($html === '') {
            return '';
        }
        if (strlen($html) > self::MAX_BYTES) {
            throw new RuntimeException('HTML-контент превышает допустимый размер 2 МБ.');
        }
        if (!class_exists(DOMDocument::class)) {
            throw new RuntimeException('Для безопасного редактирования HTML требуется PHP extension ext-dom.');
        }
        if (!mb_check_encoding($html, 'UTF-8')) {
            throw new RuntimeException('HTML должен быть корректной UTF-8 строкой.');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"><div id="uv-sanitize-root">' . $html . '</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
            );
            if ($loaded !== true) {
                throw new RuntimeException('Не удалось разобрать HTML-контент.');
            }

            $root = $this->findRoot($document);
            if (!$root instanceof DOMElement) {
                throw new RuntimeException('Не удалось создать безопасный HTML-контекст.');
            }

            $this->sanitizeChildren($root, $allowedTags);

            $result = '';
            foreach (iterator_to_array($root->childNodes) as $child) {
                $result .= $document->saveHTML($child) ?: '';
            }

            return $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function findRoot(DOMDocument $document): ?DOMElement
    {
        foreach ($document->getElementsByTagName('div') as $element) {
            if ($element instanceof DOMElement && $element->getAttribute('id') === 'uv-sanitize-root') {
                return $element;
            }
        }

        return null;
    }

    /** @param array<string, true> $allowedTags */
    private function sanitizeChildren(DOMNode $parent, array $allowedTags): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                continue;
            }

            if (!$node instanceof DOMElement) {
                $parent->removeChild($node);
                continue;
            }

            $tag = strtolower($node->tagName);
            if (isset(self::DROP_WITH_CONTENT[$tag])) {
                $parent->removeChild($node);
                continue;
            }

            $this->sanitizeChildren($node, $allowedTags);

            if (!isset($allowedTags[$tag])) {
                while ($node->firstChild !== null) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                continue;
            }

            $this->sanitizeAttributes($node, $tag);
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $names = [];
        foreach ($element->attributes as $attribute) {
            $names[] = strtolower($attribute->name);
        }

        foreach ($names as $name) {
            if (!$this->attributeAllowed($tag, $name)) {
                $element->removeAttribute($name);
                continue;
            }

            $value = trim($element->getAttribute($name));

            if ($name === 'id' && preg_match('/^[A-Za-z][A-Za-z0-9_:.-]{0,127}$/', $value) !== 1) {
                $element->removeAttribute($name);
                continue;
            }
            if ($name === 'class' && !$this->validClassList($value)) {
                $element->removeAttribute($name);
                continue;
            }
            if ($name === 'aria-hidden' && !in_array(strtolower($value), ['true', 'false'], true)) {
                $element->removeAttribute($name);
                continue;
            }
            if (in_array($name, ['href', 'src', 'cite'], true) && !$this->safeUri($value, $name !== 'src')) {
                $element->removeAttribute($name);
                continue;
            }
            if ($name === 'target' && !in_array($value, ['_blank', '_self'], true)) {
                $element->removeAttribute($name);
                continue;
            }
            if ($name === 'rel') {
                $this->sanitizeRel($element, $value);
                continue;
            }
            if (in_array($name, ['width', 'height', 'colspan', 'rowspan'], true)
                && preg_match('/^[1-9][0-9]{0,4}$/', $value) !== 1) {
                $element->removeAttribute($name);
                continue;
            }
            if ($name === 'loading' && !in_array($value, ['lazy', 'eager'], true)) {
                $element->removeAttribute($name);
                continue;
            }
            if ($name === 'decoding' && !in_array($value, ['async', 'sync', 'auto'], true)) {
                $element->removeAttribute($name);
                continue;
            }
            if ($name === 'scope' && !in_array($value, ['row', 'col', 'rowgroup', 'colgroup'], true)) {
                $element->removeAttribute($name);
            }
        }

        if ($tag === 'a' && $element->getAttribute('target') === '_blank') {
            $tokens = preg_split('/\s+/', strtolower($element->getAttribute('rel')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $tokens[] = 'noopener';
            $tokens[] = 'noreferrer';
            $element->setAttribute('rel', implode(' ', array_values(array_unique($tokens))));
        }
    }

    private function attributeAllowed(string $tag, string $name): bool
    {
        if (str_starts_with($name, 'on') || $name === 'style') {
            return false;
        }
        if (in_array($name, ['class', 'id', 'title', 'aria-hidden'], true)) {
            return true;
        }

        return match ($tag) {
            'a' => in_array($name, ['href', 'target', 'rel'], true),
            'img' => in_array($name, ['src', 'alt', 'width', 'height', 'loading', 'decoding'], true),
            'blockquote' => $name === 'cite',
            'th', 'td' => in_array($name, ['colspan', 'rowspan', 'scope'], true),
            'time' => $name === 'datetime',
            default => false,
        };
    }

    private function validClassList(string $value): bool
    {
        if ($value === '' || strlen($value) > 512) {
            return false;
        }

        foreach (preg_split('/\s+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $class) {
            if (preg_match('/^[^\x00-\x20"\'<>`=]{1,120}$/u', $class) !== 1) {
                return false;
            }
        }

        return true;
    }

    private function sanitizeRel(DOMElement $element, string $value): void
    {
        $allowed = ['noopener', 'noreferrer', 'nofollow', 'ugc', 'sponsored'];
        $tokens = preg_split('/\s+/', strtolower($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = array_values(array_unique(array_intersect($tokens, $allowed)));

        if ($tokens === []) {
            $element->removeAttribute('rel');
            return;
        }

        $element->setAttribute('rel', implode(' ', $tokens));
    }

    private function safeUri(string $value, bool $allowLinkSchemes): bool
    {
        $decoded = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($decoded === '' || preg_match('/[\x00-\x1F\x7F]/u', $decoded) === 1 || str_contains($decoded, '\\')) {
            return false;
        }
        if (str_starts_with($decoded, '#')) {
            return true;
        }
        if (str_starts_with($decoded, '//')) {
            return false;
        }

        if (preg_match('/^([A-Za-z][A-Za-z0-9+.-]*):/', $decoded, $matches) !== 1) {
            return true;
        }

        $allowed = $allowLinkSchemes
            ? ['http', 'https', 'mailto', 'tel']
            : ['http', 'https'];

        return in_array(strtolower($matches[1]), $allowed, true);
    }
}
