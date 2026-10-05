<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Documents;

use Core\Content\HtmlSanitizer;
use Core\Content\HtmlSanitizingHandler;
use Core\Delivery\DeliveryInvalidatingHandler;
use Core\Extension\Core;
use Core\Extension\ModuleInterface;
use Core\Http\Request;
use Core\Page\PageTypeDefinition;
use Core\Repository\AuditLogRepository;
use Core\Repository\LoginAttemptRepository;
use Core\Repository\UserRepository;
use Core\Security\AuthService;
use Core\Security\PermissionGate;
use Core\View\AdminPhpRendererFactory;
use Core\View\Render\TemplateFacadeContext;
use UltimaVox\Modules\Documents\Admin\DocumentAdminService;
use UltimaVox\Modules\Documents\Admin\DocumentController;
use UltimaVox\Modules\Documents\Repository\DocumentManagementRepository;
use UltimaVox\Modules\Documents\Repository\DocumentRepository;

final readonly class DocumentsModule implements ModuleInterface
{
    public function register(Core $core): void
    {
        $db = $core->runtime()->database();
        $repository = new DocumentRepository($db);
        $renderer = new DocumentRenderer($repository);

        $core->permissions()->define(
            'documents.manage',
            'Manage documents and document versions',
            ['superadmin', 'admin', 'editor'],
        );
        $core->admin()->navigation(
            'documents',
            'Документы',
            '/admin/documents',
            'documents.manage',
            25,
            'file-text',
        );
        $this->registerAdminRoutes($core, $repository);

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

    private function registerAdminRoutes(Core $core, DocumentRepository $repository): void
    {
        $db = $core->runtime()->database();
        $siteId = $core->sites()->adminId();
        $auth = new AuthService(
            new UserRepository($db),
            new LoginAttemptRepository($db),
        );
        $adminView = (new AdminPhpRendererFactory(
            $core->runtime()->rootPath(),
            $auth,
            $core->admin(),
            $core->sites(),
        ))->create([dirname(__DIR__) . '/templates']);
        $management = new DocumentManagementRepository($db, $siteId);
        $controller = new DocumentController(
            $auth,
            $management,
            new DocumentAdminService($db, $siteId, $repository, $management),
            new AuditLogRepository($db),
            $adminView,
        );
        $gate = new PermissionGate($auth);
        $html = new HtmlSanitizingHandler(new HtmlSanitizer());
        $invalidate = new DeliveryInvalidatingHandler($core->delivery());
        $routes = $core->routes();

        $siteTags = static fn (Request $request, array $variables): array => [
            'site:' . $siteId,
        ];
        $documentTags = static function (Request $request, array $variables) use ($siteId): array {
            $id = $variables['id'] ?? '';
            if (!ctype_digit($id)) {
                return ['site:' . $siteId];
            }

            $documentId = (int) $id;

            return [
                'document:' . $documentId,
                'site:' . $siteId . ':document:' . $documentId,
            ];
        };

        $routes->get('/admin/documents', 'documents.index', [$controller, 'index']);
        $routes->get('/admin/documents/create', 'documents.create', [$controller, 'createForm']);
        $routes->post(
            '/admin/documents',
            'documents.store',
            $gate->require(
                'documents.manage',
                $invalidate->wrap(
                    $html->wrap([$controller, 'store'], ['content' => 'rich']),
                    $siteTags,
                ),
            ),
        );
        $routes->get('/admin/documents/{id:\\d+}/edit', 'documents.edit', [$controller, 'editForm']);
        $routes->post(
            '/admin/documents/{id:\\d+}',
            'documents.update',
            $gate->require(
                'documents.manage',
                $invalidate->wrap(
                    $html->wrap([$controller, 'update'], ['content' => 'rich']),
                    $documentTags,
                ),
            ),
        );
    }
}
