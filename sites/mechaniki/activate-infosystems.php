#!/usr/bin/env php
<?php

declare(strict_types=1);

use Core\Database;
use Core\Database\SqlDialect;
use Core\Repository\PageSelectionRepository;
use Core\Repository\SiteRepository;
use Core\Repository\StructureRepository;
use Dotenv\Dotenv;
use UltimaVox\Modules\Infosystem\FieldSchema;
use UltimaVox\Modules\Infosystem\Repository\InfosystemManagementRepository;

$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';
require_once $root . '/modules/infosystem/autoload.php';
Dotenv::createImmutable($root)->safeLoad();

$apply = in_array('--apply', $argv, true);
$refresh = in_array('--refresh', $argv, true);
if (array_diff(array_slice($argv, 1), ['--apply', '--refresh']) !== [] || ($refresh && !$apply)) {
    fwrite(STDERR, "Usage: php sites/mechaniki/activate-infosystems.php [--apply [--refresh]]\n");
    exit(1);
}

$definitions = [
    'mechaniki_services' => [
        ['code' => 'lead', 'name' => 'Анонс', 'type' => 'textarea'],
        ['code' => 'image', 'name' => 'Изображение', 'type' => 'text'],
        ['code' => 'price_hint', 'name' => 'Ориентир стоимости', 'type' => 'text'],
    ],
    'mechaniki_symptoms' => [
        ['code' => 'lead', 'name' => 'Анонс', 'type' => 'textarea'],
        ['code' => 'image', 'name' => 'Изображение', 'type' => 'text'],
    ],
    'mechaniki_prices' => [
        ['code' => 'category', 'name' => 'Категория', 'type' => 'text', 'filterable' => true],
        ['code' => 'price_label', 'name' => 'Стоимость текстом', 'type' => 'text'],
        ['code' => 'amount_minor', 'name' => 'Стоимость в копейках', 'type' => 'number'],
        ['code' => 'currency', 'name' => 'Валюта', 'type' => 'select', 'options' => ['RUB']],
        ['code' => 'is_from', 'name' => 'Цена от', 'type' => 'boolean'],
        ['code' => 'service_href', 'name' => 'Ссылка на услугу', 'type' => 'text'],
    ],
    'mechaniki_cases' => [
        ['code' => 'year', 'name' => 'Год автомобиля', 'type' => 'text'],
        ['code' => 'symptom', 'name' => 'Симптом', 'type' => 'textarea'],
        ['code' => 'cause', 'name' => 'Причина', 'type' => 'textarea'],
        ['code' => 'work', 'name' => 'Работы', 'type' => 'textarea'],
        ['code' => 'price_label', 'name' => 'Стоимость', 'type' => 'text'],
        ['code' => 'duration', 'name' => 'Срок', 'type' => 'text'],
        ['code' => 'is_demo', 'name' => 'Демонстрационный кейс', 'type' => 'boolean'],
    ],
    'mechaniki_faq' => [
        ['code' => 'answer', 'name' => 'Ответ', 'type' => 'textarea'],
    ],
    'mechaniki_reviews' => [
        ['code' => 'text', 'name' => 'Текст отзыва', 'type' => 'textarea'],
        ['code' => 'date', 'name' => 'Дата', 'type' => 'date'],
        ['code' => 'rating', 'name' => 'Оценка', 'type' => 'number'],
        ['code' => 'source_url', 'name' => 'Первоисточник', 'type' => 'text'],
        ['code' => 'verification', 'name' => 'Тип проверки', 'type' => 'text'],
    ],
];
$views = [
    '/services' => ['mechaniki_services', 'infosystem.list', 'mechaniki.services'],
    '/problems' => ['mechaniki_symptoms', 'infosystem.list', 'mechaniki.problems'],
    '/prices' => ['mechaniki_prices', 'infosystem.list', 'mechaniki.prices'],
    '/works' => ['mechaniki_cases', 'infosystem.list', 'mechaniki.cases'],
    '/reviews' => ['mechaniki_reviews', 'infosystem.list', 'mechaniki.reviews'],
];

$fieldSchema = new FieldSchema();
$manifests = [];
$nodesRequired = array_keys($views);
foreach ($definitions as $code => $schema) {
    $source = __DIR__ . '/infosystems/' . substr($code, strlen('mechaniki_')) . '.json';
    $json = file_get_contents($source);
    if (!is_string($json)) {
        throw new RuntimeException('Missing Infosystem manifest: ' . $code);
    }

    $manifest = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($manifest) || ($manifest['code'] ?? null) !== $code
        || ($manifest['schema_version'] ?? null) !== 1
        || !is_array($manifest['records'] ?? null) || !array_is_list($manifest['records'])) {
        throw new RuntimeException('Invalid Infosystem manifest: ' . $code);
    }

    // Normalize definitions before opening any transaction.
    $schema = $fieldSchema->decode(json_encode($schema, JSON_THROW_ON_ERROR));
    $seen = [];
    foreach ($manifest['records'] as $record) {
        if (!is_array($record) || !is_string($record['slug'] ?? null)
            || preg_match('/^[a-z][a-z0-9-]{0,119}$/D', $record['slug']) !== 1
            || !is_string($record['name'] ?? $record['question'] ?? null)
            || trim((string) ($record['name'] ?? $record['question'])) === '') {
            throw new RuntimeException('Invalid record in manifest: ' . $code);
        }

        $itemPath = $record['item_path'] ?? '/' . $record['slug'];
        if (!is_string($itemPath) || !preg_match('#^/[a-z0-9-]+(?:/[a-z0-9-]+)*$#D', $itemPath)) {
            throw new RuntimeException('Invalid item path in manifest: ' . $code);
        }
        if (isset($seen[$itemPath])) {
            throw new RuntimeException('Duplicate Infosystem item path: ' . $code . $itemPath);
        }
        $seen[$itemPath] = true;

        $fieldSchema->normalizeProperties($schema, $record);
        if (isset($record['path'])) {
            if (!is_string($record['path']) || $record['path'] === '') {
                throw new RuntimeException('Invalid Structure path in manifest.');
            }
            $nodesRequired[] = $record['path'];
        }
    }
    $manifests[$code] = ['name' => (string) $manifest['name'], 'schema' => $schema, 'records' => $manifest['records']];
}

$db = Database::connection();
$site = (new SiteRepository($db))->findActiveByHost('ru.rewired.ru');
if ($site === null) {
    throw new RuntimeException('Active ru.rewired.ru site is required; no other site will be modified.');
}
$siteId = (int) $site['id'];
$state = $db->query("SELECT code, is_enabled FROM installed_modules WHERE code IN ('infosystem', 'mechaniki')");
$enabledModules = [];
foreach ($state->fetchAll() as $row) {
    $enabledModules[(string) $row['code']] = in_array($row['is_enabled'], [true, 1, '1', 't', 'true'], true);
}
if (!($enabledModules['infosystem'] ?? false) || !($enabledModules['mechaniki'] ?? false)) {
    throw new RuntimeException('Enable infosystem and mechaniki modules before activation.');
}

$structure = new StructureRepository($db, $siteId);
$nodes = [];
foreach ($structure->all() as $record) {
    $nodes[(string) $record['path']] = (int) $record['id'];
}
foreach (array_unique($nodesRequired) as $path) {
    if (!isset($nodes[$path])) {
        throw new RuntimeException('Missing Structure node ' . $path . '. Run activate.php first.');
    }
}

$count = array_sum(array_map(static fn (array $m): int => count($m['records']), $manifests));
fwrite(STDOUT, sprintf(
    "Mechaniki Infosystems: site #%d, systems %d, items %d, node bindings %d.\n",
    $siteId,
    count($manifests),
    $count,
    count(array_unique($nodesRequired)),
));
if (!$apply) {
    fwrite(STDOUT, "Dry run only; no database changes. Use --apply after backup.\n");
    exit(0);
}

$management = new InfosystemManagementRepository($db, $siteId);
$pageSelections = new PageSelectionRepository($db);
$existingSystems = [];
foreach ($management->all() as $system) {
    $existingSystems[(string) $system['code']] = (int) $system['id'];
}
$createdItems = 0;
$updatedItems = 0;
$bindings = [];
$db->beginTransaction();
try {
    foreach ($manifests as $code => $manifest) {
        $infosystemId = $existingSystems[$code]
            ?? $management->create($manifest['name'], $code, null, $manifest['schema'], true);
        $currentItems = [];
        foreach ($management->items($infosystemId, 500) as $item) {
            $currentItems[(string) $item['path']] = $item;
        }

        $groups = [];
        foreach ($management->groups($infosystemId) as $group) {
            $groups[(string) $group['path']] = (int) $group['id'];
        }
        if ($code === 'mechaniki_services') {
            $topLevel = [];
            foreach ($manifest['records'] as $record) {
                if (($record['group'] ?? null) === null) {
                    $topLevel[(string) $record['slug']] = (string) $record['name'];
                }
            }
            foreach ($manifest['records'] as $record) {
                $groupSlug = $record['group'] ?? null;
                if ($groupSlug === null) {
                    continue;
                }
                if (!is_string($groupSlug) || !preg_match('/^[a-z0-9-]+$/D', $groupSlug)) {
                    throw new RuntimeException('Unsupported nested service group path.');
                }
                $groupPath = '/' . $groupSlug;
                if (!isset($groups[$groupPath])) {
                    $groups[$groupPath] = $management->createGroup(
                        $infosystemId, null, $topLevel[$groupSlug] ?? $groupSlug,
                        $groupSlug, null, 0, true,
                    );
                }
            }
        }

        foreach ($manifest['records'] as $index => $record) {
            $itemPath = (string) ($record['item_path'] ?? '/' . $record['slug']);
            $groupId = null;
            if (isset($record['group'])) {
                $groupId = $groups['/' . $record['group']] ?? null;
                if ($groupId === null) {
                    throw new RuntimeException('Missing service category: ' . $record['group']);
                }
            }
            $properties = $fieldSchema->normalizeProperties($manifest['schema'], $record);
            $content = '';
            if (isset($record['source_file'])) {
                $sourceFile = (string) $record['source_file'];
                if (!preg_match('#^pages/(services|problems)/[a-z0-9/-]+\.html$#D', $sourceFile)) {
                    throw new RuntimeException('Invalid source page location.');
                }
                $content = file_get_contents(__DIR__ . '/' . $sourceFile);
                if (!is_string($content) || str_contains($content, '<?')
                    || preg_match('/<script\b/i', $content) === 1) {
                    throw new RuntimeException('Unsafe or unreadable source page: ' . $sourceFile);
                }

                $content = preg_replace(
                    '/^\s*<nav\b[\s\S]*?<\/nav>\s*<section\b[^>]*class="[^"]*page-hero[^"]*"[^>]*>[\s\S]*?<\/section>/i',
                    '',
                    $content,
                    1,
                );
                if (!is_string($content)) {
                    throw new RuntimeException('Unable to extract item body.');
                }
                $content = preg_replace('/<form\b/i', '<form inert aria-disabled="true"', $content);
                if (!is_string($content)) {
                    throw new RuntimeException('Unable to disable source form.');
                }
            }

            $existing = $currentItems[$itemPath] ?? null;
            if ($existing !== null && !$refresh) {
                // A normal replay must never overwrite an editor's changes.
            } else {
                $name = (string) ($record['name'] ?? $record['question'] ?? '');
                $data = [
                    'name' => $name,
                    'slug' => (string) $record['slug'],
                    'description' => isset($record['lead']) ? '<p>' . htmlspecialchars((string) $record['lead'], ENT_QUOTES, 'UTF-8') . '</p>' : null,
                    'content' => $content,
                    'meta_description' => isset($record['lead']) ? (string) $record['lead'] : null,
                    'status' => 'published',
                    'is_active' => true,
                    'sorting' => (int) ($record['sorting'] ?? ($index + 1) * 10),
                    'properties' => $properties,
                    'publish_at' => null,
                ];
                $management->saveItem(
                    $existing === null ? null : (int) $existing['id'],
                    $infosystemId,
                    $groupId,
                    $data,
                );
                if ($existing === null) {
                    $createdItems++;
                } else {
                    $updatedItems++;
                }
            }

            if (isset($record['path'])) {
                $views[(string) $record['path']] = [
                    $code, 'infosystem.item',
                    $code === 'mechaniki_services' ? 'mechaniki.service.item' : 'mechaniki.problem.item',
                    $itemPath,
                ];
            }
        }
    }

    $bindingSql = 'INSERT INTO node_module_bindings (node_id, module_code, binding_code, target_key, updated_at) '
        . "VALUES (:node_id, 'infosystem', 'primary', :target_key, CURRENT_TIMESTAMP) "
        . SqlDialect::upsert($db, ['node_id', 'module_code', 'binding_code'], ['target_key', 'updated_at']);
    $bind = $db->prepare($bindingSql);
    foreach ($views as $path => $definition) {
        [$code, $pageType, $viewCode] = $definition;
        $nodeId = $nodes[$path];
        $node = $structure->find($nodeId);
        if ($node === null || !in_array((string) $node['page_type'], ['documents.page', 'infosystem.item', 'infosystem.list'], true)) {
            throw new RuntimeException('Refusing to replace unrelated page: ' . $path);
        }

        $configuration = $pageType === 'infosystem.item'
            ? ['item_path' => $definition[3], 'view' => $viewCode]
            : ['view' => $viewCode, 'limit' => 100];
        $pageSelections->assign($nodeId, $siteId, $pageType, $configuration);
        $bind->execute(['node_id' => $nodeId, 'target_key' => $code]);
        $bindings[] = $path;
    }

    $db->commit();
} catch (Throwable $exception) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $exception;
}

fwrite(STDOUT, sprintf(
    "Infosystems ready: %d items created, %d updated, %d Structure bindings.\n",
    $createdItems,
    $updatedItems,
    count($bindings),
));
fwrite(STDOUT, "Clear the site page cache and verify all routes before marking staging ready.\n");
