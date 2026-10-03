<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Menu;

use Core\Extension\Api\ExtensionsApi;
use Core\Site\SiteContext;
use Core\View\Render\RenderContext;
use Core\View\Render\RenderSource;
use Core\View\Render\TemplateFacadeContext;
use JsonException;
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
        private readonly ExtensionsApi $extensions,
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

        $variables = $this->templateContext->variables();
        $site = $variables['site'] ?? null;
        if (!$site instanceof SiteContext || $site->id !== $siteId) {
            throw new RuntimeException('Menu render source requires the matching current site context.');
        }
        $currentNode = $variables['node'] ?? [];
        if (!is_array($currentNode)) {
            $currentNode = [];
        }

        $rows = $this->repository->findItems($menuId);
        $currentPath = $this->normalizeInternalPath((string) ($currentNode['path'] ?? '/')) ?? '/';
        $sourceContext = new NavigationSourceContext(
            $site,
            $context,
            $currentNode,
            $menuId,
            $code,
        );
        $navigation = $this->buildNavigationTree($rows, $sourceContext);
        $items = $this->decorateNodes($navigation, $currentPath);

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

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<NavigationNode>
     */
    private function buildNavigationTree(array $rows, NavigationSourceContext $sourceContext): array
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
        ) use (&$walk, &$visited, $children, $sourceContext): array {
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
                $kind = (string) ($row['kind'] ?? '');

                if ($kind === 'source') {
                    if (($children[$id] ?? []) !== []) {
                        throw new RuntimeException('Dynamic Menu source items cannot contain child menu items.');
                    }
                    if (!$visible) {
                        continue;
                    }

                    foreach ($this->resolveSource($row, $sourceContext) as $node) {
                        $result[] = $node;
                    }
                    continue;
                }

                if ($kind !== 'link') {
                    throw new RuntimeException(sprintf('Unsupported Menu item kind: %s.', $kind));
                }

                $childNodes = $walk($id, $visible, $depth + 1, $nextTrail);
                if (!$visible) {
                    continue;
                }

                $result[] = new NavigationNode(
                    'menu-item:' . $id,
                    (string) ($row['label'] ?? ''),
                    trim((string) ($row['url'] ?? '')),
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

    /** @param array<string, mixed> $row @return list<NavigationNode> */
    private function resolveSource(array $row, NavigationSourceContext $context): array
    {
        $sourceCode = strtolower(trim((string) ($row['source_code'] ?? '')));
        if (!preg_match('/^[a-z][a-z0-9._-]{0,127}$/', $sourceCode)) {
            throw new RuntimeException('Dynamic Menu source code is invalid.');
        }

        $source = $this->extensions->get(NavigationSourceInterface::EXTENSION_POINT, $sourceCode);
        if (!$source instanceof NavigationSourceInterface) {
            throw new RuntimeException(sprintf(
                'Menu navigation source "%s" must implement NavigationSourceInterface.',
                $sourceCode,
            ));
        }

        $nodes = $source->resolve($context, $this->decodeSourceConfiguration($row['source_config'] ?? null));
        foreach ($nodes as $node) {
            if (!$node instanceof NavigationNode) {
                throw new RuntimeException(sprintf(
                    'Menu navigation source "%s" returned an invalid node.',
                    $sourceCode,
                ));
            }
        }

        return $nodes;
    }

    /** @return array<string, mixed> */
    private function decodeSourceConfiguration(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Menu source configuration contains invalid JSON.', 0, $exception);
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException('Menu source configuration must be a JSON object.');
        }

        return $decoded;
    }

    /** @param list<NavigationNode> $nodes @return list<MenuNode> */
    private function decorateNodes(array $nodes, string $currentPath, int $depth = 1): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw new RuntimeException('Resolved Menu navigation exceeds the maximum depth.');
        }

        $result = [];
        foreach ($nodes as $node) {
            $children = $this->decorateNodes($node->children, $currentPath, $depth + 1);
            $normalizedUrl = $this->normalizeInternalPath($node->url);
            $current = $normalizedUrl !== null && $normalizedUrl === $currentPath;
            $active = $current
                || ($normalizedUrl !== null
                    && $normalizedUrl !== '/'
                    && str_starts_with($currentPath, $normalizedUrl))
                || $this->hasActiveChild($children);

            $result[] = new MenuNode(
                $node->key,
                $node->label,
                $node->url,
                $current,
                $active,
                $children,
            );
        }

        return $result;
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
