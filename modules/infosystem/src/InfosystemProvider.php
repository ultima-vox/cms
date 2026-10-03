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
                    'include_content' => [
                        'type' => 'boolean',
                        'title' => 'Выводить содержимое узла перед списком',
                    ],
                ],
                'additionalProperties' => false,
            ],
            configurationValidator: new InfosystemPageConfigurationValidator(),
            sorting: 20,
            description: 'Список элементов информационной системы, связанной с узлом структуры.',
        ));
    }
}
