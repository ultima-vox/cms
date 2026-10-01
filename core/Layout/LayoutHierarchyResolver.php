<?php

declare(strict_types=1);

namespace Core\Layout;

use Core\Repository\LayoutRepository;
use RuntimeException;

final readonly class LayoutHierarchyResolver
{
    public const MAX_LAYOUT_STAGES = 31;

    public function __construct(private LayoutRepository $layouts)
    {
    }

    /** @return list<LayoutDefinition> Outermost layout first, selected layout last. */
    public function resolveDefault(): array
    {
        $layoutId = $this->layouts->defaultSystemId();
        if ($layoutId === null) {
            throw new RuntimeException('Default system layout was not found.');
        }

        return $this->resolve($layoutId);
    }

    /** @return list<LayoutDefinition> Outermost layout first, selected layout last. */
    public function resolve(int $layoutId): array
    {
        $rows = $this->layouts->ancestry($layoutId, self::MAX_LAYOUT_STAGES);
        if ($rows === []) {
            throw new RuntimeException(sprintf('Layout #%d was not found.', $layoutId));
        }

        $definitions = [];
        foreach ($rows as $row) {
            $depth = (int) ($row['depth'] ?? 0);
            if ($depth > self::MAX_LAYOUT_STAGES) {
                throw new RuntimeException(sprintf(
                    'Layout hierarchy exceeds the maximum depth of %d layouts.',
                    self::MAX_LAYOUT_STAGES,
                ));
            }

            if ($this->boolean($row['cycle'] ?? false)) {
                throw new RuntimeException('Circular layout hierarchy detected.');
            }

            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                throw new RuntimeException('Layout hierarchy contains an invalid layout id.');
            }

            $parentId = isset($row['parent_id']) ? (int) $row['parent_id'] : null;
            $definitions[] = new LayoutDefinition(
                id: $id,
                parentId: $parentId !== null && $parentId > 0 ? $parentId : null,
                name: (string) ($row['name'] ?? ''),
                templatePath: (string) ($row['template_path'] ?? ''),
            );
        }

        if ($definitions[0]->parentId !== null) {
            throw new RuntimeException('Layout hierarchy does not terminate at a root layout.');
        }

        $selected = $definitions[array_key_last($definitions)];
        if ($selected->id !== $layoutId) {
            throw new RuntimeException('Layout hierarchy does not terminate at the selected layout.');
        }

        return $definitions;
    }

    private function boolean(mixed $value): bool
    {
        return $value === true
            || $value === 1
            || $value === '1'
            || $value === 't'
            || $value === 'true';
    }
}
