<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Documents;

use Core\Extension\Core;
use Core\Extension\ModuleInterface;
use Core\Page\PageTypeDefinition;
use Core\View\Render\TemplateFacadeContext;
use UltimaVox\Modules\Documents\Repository\DocumentRepository;

final readonly class DocumentsModule implements ModuleInterface
{
    public function register(Core $core): void
    {
        $repository = new DocumentRepository($core->runtime()->database());
        $renderer = new DocumentRenderer($repository);

        $core->templates()->facade(
            'documents',
            static fn (TemplateFacadeContext $context): DocumentsFacade => new DocumentsFacade(
                $context,
                $repository,
                $renderer,
            ),
        );

        $core->pages()->type(new PageTypeDefinition(
            code: 'documents.page',
            name: 'Документ',
            executor: new DocumentsPageExecutor($repository, $renderer),
            configurationSchema: [
                '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                'type' => 'object',
                'properties' => [
                    'document' => [
                        'type' => 'string',
                        'title' => 'Код документа',
                        'pattern' => '^[a-z][a-z0-9_-]{0,119}$',
                    ],
                ],
                'required' => ['document'],
                'additionalProperties' => false,
            ],
            configurationValidator: new DocumentsPageConfigurationValidator(),
            provisioner: new DocumentsPageProvisioner($repository),
            isDefault: true,
            sorting: 10,
            description: 'Страница на основе опубликованной версии документа.',
        ));
    }
}
