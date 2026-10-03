<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Menu;

use Core\Site\SiteContext;
use Core\View\Render\TemplateFacadeContext;
use RuntimeException;
use UltimaVox\Modules\Menu\Repository\MenuRepository;

final class MenusFacade
{
    /** @var array<string, MenuFacade> */
    private array $resolved = [];

    public function __construct(
        private readonly TemplateFacadeContext $context,
        private readonly MenuRepository $repository,
    ) {
    }

    public function get(string $code): MenuFacade
    {
        $code = strtolower(trim($code));
        if (isset($this->resolved[$code])) {
            return $this->resolved[$code];
        }

        $site = $this->context->variables()['site'] ?? null;
        if (!$site instanceof SiteContext) {
            throw new RuntimeException('Menus facade requires the current site context.');
        }

        $menu = $this->repository->findActiveByCode($site->id, $code);
        if ($menu === null) {
            throw new RuntimeException(sprintf('Menu "%s" was not found for the current site.', $code));
        }

        return $this->resolved[$code] = new MenuFacade(
            $this->context,
            $this->repository,
            $menu,
        );
    }
}
