<?php

declare(strict_types=1);

namespace Core\View;

final readonly class PageViewModel
{
    public function __construct(
        public int $id,
        public string $name,
        public string $title,
        public string $path,
        public SafeHtml $content,
        public ?string $metaDescription,
    ) {
    }
}
