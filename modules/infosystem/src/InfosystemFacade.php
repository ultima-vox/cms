<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem;

use Core\Extension\Api\ContentApi;
use Core\View\Render\TemplateFacadeContext;
use LogicException;

final readonly class InfosystemFacade
{
    /** @param array<string, mixed> $record */
    public function __construct(
        private array $record,
        private ContentApi $content,
        private TemplateFacadeContext $context,
    ) {
    }

    public function id(): int
    {
        return (int) $this->record['id'];
    }

    public function code(): string
    {
        return (string) $this->record['code'];
    }

    public function name(): string
    {
        return (string) $this->record['name'];
    }

    public function items(): InfosystemItemsSource
    {
        $source = $this->content->make(
            'infosystem.items',
            $this->context,
            ['infosystem' => $this->record],
        );

        if (!$source instanceof InfosystemItemsSource) {
            throw new LogicException('infosystem.items source returned an unexpected implementation.');
        }

        return $source;
    }
}
