<?php

declare(strict_types=1);

namespace Core\View;

final readonly class SafeHtml
{
    private function __construct(private string $value)
    {
    }

    /**
     * Transitional boundary for HTML already stored by the CMS.
     * New editor writes must pass through the sanitizer before reaching this boundary.
     */
    public static function fromTrustedStorage(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
