<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Menu;

use Core\Extension\Api\ExtensionsApi;
use Core\View\Render\TemplateFacadeContext;
use UltimaVox\Modules\Menu\Repository\MenuRepository;

final readonly class MenuFacade
{
    /** @param array<string, mixed> $menu */
    public function __construct(
        private TemplateFacadeContext $context,
        private MenuRepository $repository,
        private ExtensionsApi $extensions,
        private array $menu,
    ) {
    }

    public function id(): int
    {
        return (int) $this->menu['id'];
    }

    public function code(): string
    {
        return (string) $this->menu['code'];
    }

    public function name(): string
    {
        return (string) $this->menu['name'];
    }

    public function view(string $viewCode): MenuSource
    {
        return $this->source()->view($viewCode);
    }

    public function show(): string
    {
        return $this->source()->show();
    }

    private function source(): MenuSource
    {
        return new MenuSource(
            $this->context,
            $this->repository,
            $this->extensions,
            $this->menu,
        );
    }
}
