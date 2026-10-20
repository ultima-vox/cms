<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem;

use Core\Extension\Core;
use Core\Extension\ModuleInterface;
use Core\Page\PageTypeDefinition;
use Core\Repository\NodeModuleBindingRepository;
use UltimaVox\Modules\Infosystem\Repository\InfosystemRepository;

final readonly class InfosystemProvider implements ModuleInterface
{
    public function register(Core $core): void
    {
        (new InfosystemModule())->register($core);

        $db = $core->runtime()->database();
        $editor = new InfosystemPageEditorLinkProvider(
            new InfosystemRepository($db),
            new \UltimaVox\Modules\Infosystem\Repository\InfosystemManagementRepository($db, $core->sites()->adminId()),
            new NodeModuleBindingRepository($db),
            $core->sites()->adminId(),
        );
        $core->pages()->type(new PageTypeDefinition(
            code: 'infosystem.list',
            name: 'Информационная система',
            executor: new InfosystemPageExecutor(
                new InfosystemRepository($db),
                new NodeModuleBindingRepository($db),
                $core->events(),
                $core->templates(),
                $core->runtime()->rootPath(),
            ),
            configurationSchema: [
                '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                'type' => 'object',
                'properties' => [
                    'view' => [
                        'type' => 'string',
                        'title' => 'Шаблон вывода',
                        'pattern' => '^[a-z0-9][a-z0-9._-]{0,127}$',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'title' => 'Количество элементов',
                        'minimum' => 1,
                        'maximum' => 500,
                    ],
                ],
                'additionalProperties' => false,
            ],
            configurationValidator: new InfosystemPageConfigurationValidator(),
            sorting: 20,
            description: 'Список элементов информационной системы, связанной с узлом структуры.',
            editorLinkProvider: $editor,
        ));
        $core->pages()->type(new PageTypeDefinition(
            code: 'infosystem.item',
            name: 'Элемент информационной системы',
            executor: new InfosystemItemPageExecutor(
                new InfosystemRepository($db),
                new NodeModuleBindingRepository($db),
                $core->templates(),
                $core->runtime()->rootPath(),
            ),
            configurationSchema: [
                '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                'type' => 'object',
                'properties' => [
                    'item_path' => [
                        'type' => 'string',
                        'title' => 'Путь элемента в ИС',
                        'pattern' => '^/[a-z0-9-]+(/[a-z0-9-]+)*$',
                        'maxLength' => 255,
                    ],
                    'view' => [
                        'type' => 'string',
                        'title' => 'Шаблон карточки',
                        'pattern' => '^[a-z0-9][a-z0-9._-]{0,127}$',
                    ],
                ],
                'required' => ['item_path'],
                'additionalProperties' => false,
            ],
            configurationValidator: new InfosystemItemPageConfigurationValidator(),
            sorting: 21,
            description: 'Опубликованный элемент привязанной информационной системы.',
            editorLinkProvider: $editor,
        ));
    }
}
