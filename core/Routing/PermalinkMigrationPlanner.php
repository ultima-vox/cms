<?php

declare(strict_types=1);

namespace Core\Routing;

use RuntimeException;

final class PermalinkMigrationPlanner
{
    /**
     * @param list<array{id:int|string,path:string}> $nodes All nodes in exactly one site.
     * @return array<string, mixed>
     */
    public function plan(array $nodes, string $style): array
    {
        new Permalink('/', $style);
        $validated = [];
        $seen = [];
        foreach ($nodes as $node) {
            $id = $node['id'] ?? null;
            $path = $node['path'] ?? null;
            if (
                (!is_int($id) && !(is_string($id) && ctype_digit($id)))
                || (int) $id < 1 || !is_string($path) || isset($seen[(int) $id])
            ) {
                throw new RuntimeException('URL planning requires unique positive node ids and string paths.');
            }
            $seen[(int) $id] = true;
            $validated[] = ['id' => (int) $id, 'path' => $path];
        }
        usort(
            $validated,
            static fn (array $a, array $b): int => [$a['path'], $a['id']] <=> [$b['path'], $b['id']],
        );
        $entries = [];
        $sources = [];
        $targets = [];
        $files = [];
        $conflicts = [];
        $addConflict = static function (string $type, string $path, array $ids, ?string $message = null) use (&$conflicts): void {
            $ids = array_values(array_unique($ids));
            sort($ids, SORT_NUMERIC);
            $key = $type . ':' . $path . ':' . implode(',', $ids);
            $conflicts[$key] = ['type' => $type, 'path' => $path, 'node_ids' => $ids];
            if ($message !== null) {
                $conflicts[$key]['message'] = $message;
            }
        };
        foreach ($validated as $node) {
            $id = $node['id'];
            $path = $node['path'];
            $sources[$path][] = $id;
            try {
                $permalink = new Permalink($path, $style);
            } catch (RuntimeException $exception) {
                $addConflict('invalid_path', $path, [$id], $exception->getMessage());
                continue;
            }
            $url = $permalink->url();
            $file = $permalink->relativeFile();
            $entries[] = ['node_id' => $id, 'source' => $path, 'target' => $url, 'output_file' => $file];
            $targets[$url][] = $id;
            $files[$file][] = $id;
        }
        foreach ($targets as $url => $ids) {
            if (count($ids) > 1) {
                $addConflict('target_collision', $url, $ids);
                continue;
            }
            $otherSources = array_diff($sources[$url] ?? [], $ids);
            if ($otherSources !== []) {
                $addConflict('redirect_shadow', $url, array_merge($ids, $otherSources));
            }
        }
        foreach ($files as $file => $ids) {
            $parent = dirname($file);
            while ($parent !== '.') {
                if (isset($files[$parent])) {
                    $addConflict('file_directory_collision', $parent, array_merge($ids, $files[$parent]));
                }
                $parent = dirname($parent);
            }
        }
        $redirects = [];
        if ($conflicts === []) {
            foreach ($entries as $entry) {
                if ($entry['source'] !== $entry['target']) {
                    $redirects[] = ['node_id' => $entry['node_id'], 'from' => $entry['source'], 'to' => $entry['target']];
                }
            }
        }
        return [
            'scope' => 'structure_nodes_including_unpublished',
            'public_route_activation' => false,
            'style' => $style,
            'nodes_checked' => count($nodes),
            'valid' => $conflicts === [],
            'entries' => $entries,
            'candidate_redirects' => $redirects,
            'conflicts' => array_values($conflicts),
        ];
    }
}
