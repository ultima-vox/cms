<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Documents;

use Core\Extension\Core;
use Core\Extension\ModuleInterface;
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

        $core->pages()->executor(
            'documents.page',
            new DocumentsPageExecutor($repository, $renderer),
        );
    }
}
