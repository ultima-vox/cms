<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem;

use Core\Extension\Api\ContentApi;
use Core\Repository\NodeModuleBindingRepository;
use Core\Site\SiteContext;
use Core\View\Render\TemplateFacadeContext;
use RuntimeException;
use UltimaVox\Modules\Infosystem\Repository\InfosystemRepository;

final class InfosystemsFacade
{
    /** @var array<int, InfosystemFacade> */
    private array $byId = [];

    /** @var array<string, InfosystemFacade> */
    private array $byCode = [];

    public function __construct(
        private readonly InfosystemRepository $repository,
        private readonly NodeModuleBindingRepository $bindings,
        private readonly ContentApi $content,
        private readonly TemplateFacadeContext $context,
    ) {
    }

    public function get(string $code): InfosystemFacade
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            throw new RuntimeException('Infosystem code is required.');
        }

        if (isset($this->byCode[$code])) {
            return $this->byCode[$code];
        }

        $record = $this->repository->findActiveByCode($this->siteId(), $code);
        if ($record === null) {
            throw new RuntimeException(sprintf('Active infosystem "%s" was not found for the current site.', $code));
        }

        return $this->remember($record);
    }

    public function linked(): ?InfosystemFacade
    {
        $node = $this->context->variables()['node'] ?? null;
        if (!is_array($node)) {
            return null;
        }

        $nodeId = isset($node['id']) && is_numeric($node['id']) ? (int) $node['id'] : 0;
        if ($nodeId < 1) {
            return null;
        }

        $code = $this->bindings->findTargetKey($nodeId, 'infosystem', 'primary');
        if ($code === null) {
            return null;
        }

        if (isset($this->byCode[$code])) {
            return $this->byCode[$code];
        }

        $record = $this->repository->findActiveByCode($this->siteId(), $code);

        return $record !== null ? $this->remember($record) : null;
    }

    private function siteId(): int
    {
        $site = $this->context->variables()['site'] ?? null;
        if (!$site instanceof SiteContext) {
            throw new RuntimeException('Infosystem frontend access requires a resolved SiteContext.');
        }

        return $site->id;
    }

    /** @param array<string, mixed> $record */
    private function remember(array $record): InfosystemFacade
    {
        $id = (int) $record['id'];
        $code = (string) $record['code'];
        $facade = $this->byId[$id] ?? new InfosystemFacade(
            $record,
            $this->content,
            $this->context,
        );

        $this->byId[$id] = $facade;
        $this->byCode[$code] = $facade;

        return $facade;
    }
}
