<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Menu;

use Core\View\Render\RenderContext;
use Core\View\Render\RenderSource;
use Core\View\Render\TemplateFacadeContext;
use RuntimeException;
use UltimaVox\Modules\Menu\Repository\MenuRepository;

final class MenuSource extends RenderSource
{
    private const MAX_DEPTH = 32;

    private string $viewCode = 'menu.default';

    /** @param array<string, mixed> $menu */
    public function __construct(
        private readonly TemplateFacadeContext $templateContext,
        private readonly MenuRepository $repository,
        private readonly array $menu,
    ) {
        parent::__construct($templateContext->renderEngine());
    }

    public function view(string $viewCode): self
    {
        $viewCode = strtolower(trim($viewCode));
        if ($viewCode !== '' && !str_contains($viewCode, '.')) {
            $viewCode = 'menu.' . $viewCode;
        }
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,127}$/', $viewCode)) {
            throw new RuntimeException('Menu view code is invalid.');
        }

        $clone = clone $this;
        $clone->viewCode = $viewCode;

        return $clone;
    }

    public function render(RenderContext $context): string
    {
        $menuId = (int) ($this->menu['id'] ?? 0);
        $siteId = (int) ($this->menu['site_id'] ?? 0);
        $code = (string) ($this->menu['code'] ?? '');
        if ($menuId < 1 || $siteId < 1 || $code === '') {
            throw new RuntimeException('Menu render source requires a site-scoped menu.');
        }

        $rows = $this->repository->findLinkItems($menuId);
        $currentPath = $this->currentPath();
        $items = $this->buildTree($rows, $currentPath);

        $context->dependency('menu:' . $menuId);
        $context->dependency('site:' . $siteId . ':menu:' . $menuId);
        $context->dependency('site:' . $siteId . ':menu:' . $code);
        foreach ($rows as $row) {
            $itemId = isset($row['id']) ? (int) $row['id'] : 0;
            if ($itemId > 0) {
                $context->dependency('menu_item:' . $itemId);
            }
        }

        return $this->templateContext->renderView(
            $this->viewCode,
            'menu.tree',
            [
                'menu' => new MenuViewModel(
                    $menuId,
                    $code,
                    (string) ($this->menu['name'] ?? $code),
                    $items,
                ),
            ],
        );
    }

    private function currentPath(): string
    {
        $node = $this->templateContext->variables()['node'] ?? null;
        if (!is_array($node)) {
            return '/';
        }

        return $this->normalizeInternalPath((string) ($node['path'] ?? '/')) ?? '/';
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<MenuNode>
     */
    private function buildTree(array $rows, string $currentPath): array
    {
        /** @var array<int, list<array<string, mixed>>> $children */
        $children = [];
        foreach ($rows as $row) {
            $parentId = $row['parent_id'] === null ? 0 : (int) $row['parent_id'];
            $children[$parentId][] = $row;
        }

        /** @var array<int, true> $visited */
        $visited = [];

        $walk = function (
            int $parentId,
            bool $parentVisible,
            int $depth,
            array $trail,
        ) use (&$walk, &$visited, $children, $currentPath): array {
            if ($depth > self::MAX_DEPTH) {
                throw new RuntimeException('Menu tree exceeds the maximum depth.');
            }

            $result = [];
            foreach ($children[$parentId] ?? [] as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id < 1) {
                    throw new RuntimeException('Menu item id is invalid.');
                }
                if (isset($trail[$id])) {
                    throw new RuntimeException('Circular Menu item hierarchy detected.');
                }

                $visited[$id] = true;
                $nextTrail = $trail;
                $nextTrail[$id] = true;
                $visible = $parentVisible && $this->databaseBoolean($row['is_active'] ?? false);
                $childNodes = $walk($id, $visible, $depth + 1, $nextTrail);
                if (!$visible) {
                    continue;
                }

                $url = trim((string) ($row['url'] ?? ''));
                $normalizedUrl = $this->normalizeInternalPath($url);
                $current = $normalizedUrl !== null && $normalizedUrl === $currentPath;
                $active = $current
                    || ($normalizedUrl !== null
                        && $normalizedUrl !== '/'
                        && str_starts_with($currentPath, $normalizedUrl))
                    || $this->hasActiveChild($childNodes);

                $result[] = new MenuNode(
                    $id,
                    (string) ($row['label'] ?? ''),
                    $url,
                    $current,
                    $active,
                    $childNodes,
                );
            }

            return $result;
        };

        $tree = $walk(0, true, 1, []);
        if (count($visited) !== count($rows)) {
            throw new RuntimeException('Menu tree contains a cycle or unreachable items.');
        }

        return $tree;
    }

    /** @param list<MenuNode> $nodes */
    private function hasActiveChild(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if ($node->active) {
                return true;
            }
        }

        return false;
    }

    private function normalizeInternalPath(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || !str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '' || $path[0] !== '/') {
            return null;
        }

        return $path === '/' ? '/' : rtrim($path, '/') . '/';
    }

    private function databaseBoolean(mixed $value): bool
    {
        return $value === true
            || $value === 1
            || $value === '1'
            || $value === 't'
            || $value === 'true';
    }
}
