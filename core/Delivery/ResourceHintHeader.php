<?php

declare(strict_types=1);

namespace Core\Delivery;

final class ResourceHintHeader
{
    private function __construct()
    {
    }

    /** @param list<ResourceHint> $hints */
    public static function render(array $hints): ?string
    {
        if ($hints === []) {
            return null;
        }

        $parts = [];
        foreach ($hints as $hint) {
            if (!$hint instanceof ResourceHint) {
                continue;
            }

            $part = '<' . $hint->href . '>; rel="' . self::quoted($hint->rel) . '"';
            foreach ($hint->attributes as $name => $value) {
                $part .= '; ' . $name . '="' . self::quoted($value) . '"';
            }
            $parts[] = $part;
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    private static function quoted(string $value): string
    {
        return addcslashes($value, "\\\"");
    }
}
