<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem;

use Core\Extension\Api\EventsApi;
use Core\Repository\InfosystemRepository;
use Core\View\Render\RenderContext;
use Core\View\Render\RenderSource;
use Core\View\Render\TemplateFacadeContext;
use RuntimeException;
use UltimaVox\Modules\Infosystem\Event\InfosystemItemsRendered;

final class InfosystemItemsSource extends RenderSource
{
    private int $limit = 100;
    private int $offset = 0;

    /** @var array<string, scalar|null> */
    private array $filters = [];

    private string $viewCode = 'infosystem.list';

    /** @param array<string, mixed> $infosystem */
    public function __construct(
        private readonly TemplateFacadeContext $templateContext,
        private readonly InfosystemRepository $repository,
        private readonly EventsApi $events,
        private readonly array $infosystem,
    ) {
        parent::__construct($templateContext->renderEngine());
    }

    public function limit(int $limit): self
    {
        if ($limit < 1 || $limit > 500) {
            throw new RuntimeException('Infosystem item limit must be in the range 1..500.');
        }

        $clone = clone $this;
        $clone->limit = $limit;

        return $clone;
    }

    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new RuntimeException('Infosystem item offset cannot be negative.');
        }

        $clone = clone $this;
        $clone->offset = $offset;

        return $clone;
    }

    public function where(string $field, string|int|float|bool|null $value): self
    {
        $field = trim($field);
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,79}$/', $field)) {
            throw new RuntimeException('Infosystem property filter field is invalid.');
        }

        $clone = clone $this;
        $clone->filters[$field] = $value;

        return $clone;
    }

    /** @param array<string, scalar|null> $filters */
    public function filter(array $filters): self
    {
        $source = $this;
        foreach ($filters as $field => $value) {
            if (!is_string($field)) {
                throw new RuntimeException('Infosystem property filter field must be a string.');
            }
            if (!is_scalar($value) && $value !== null) {
                throw new RuntimeException('Infosystem property filter value must be scalar or null.');
            }
            $source = $source->where($field, $value);
        }

        return $source;
    }

    public function template(string $viewCode): self
    {
        $viewCode = trim($viewCode);
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,127}$/', $viewCode)) {
            throw new RuntimeException('Infosystem view code is invalid.');
        }

        $clone = clone $this;
        $clone->viewCode = $viewCode;

        return $clone;
    }

    public function render(RenderContext $context): string
    {
        $siteId = (int) ($this->infosystem['site_id'] ?? 0);
        $infosystemId = (int) $this->infosystem['id'];
        if ($siteId < 1) {
            throw new RuntimeException('Infosystem render source requires a site-scoped record.');
        }

        $items = $this->repository->findPublishedItems(
            $siteId,
            $infosystemId,
            $this->limit,
            $this->offset,
            $this->filters,
        );

        $context->dependency('site:' . $siteId);
        $context->dependency('infosystem:' . $infosystemId);
        $context->dependency('site:' . $siteId . ':infosystem:' . $infosystemId);
        foreach ($items as $item) {
            if (isset($item['id'])) {
                $itemId = (int) $item['id'];
                $context->dependency('infosystem_item:' . $itemId);
                $context->dependency('site:' . $siteId . ':infosystem_item:' . $itemId);
            }
        }

        $html = $this->templateContext->renderView(
            $this->viewCode,
            'infosystem.items',
            [
                'infosystem' => $this->infosystem,
                'items' => $items,
            ],
        );

        $this->events->dispatch(new InfosystemItemsRendered(
            $infosystemId,
            count($items),
            $this->viewCode,
        ));

        return $html;
    }
}
