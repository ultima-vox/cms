<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Mechaniki;

use Core\Content\HtmlSanitizer;
use Core\Extension\Core;
use Core\Extension\ModuleInterface;
use Core\Http\Request;
use Core\Http\Response;
use Core\Repository\AuditLogRepository;
use Core\Repository\LayoutRepository;
use Core\Repository\LoginAttemptRepository;
use Core\Repository\PageSelectionRepository;
use Core\Repository\StructureRepository;
use Core\Repository\UserRepository;
use Core\Security\AuthService;
use Core\Security\Csrf;
use Core\Structure\StructurePageProvisioningService;
use Core\View\AdminPhpRendererFactory;
use RuntimeException;
use Throwable;
use UltimaVox\Modules\Documents\Repository\DocumentRepository;

final readonly class MechanikiModule implements ModuleInterface
{
    public function register(Core $core): void
    {
        $db = $core->runtime()->database();
        $auth = new AuthService(new UserRepository($db), new LoginAttemptRepository($db));
        $view = (new AdminPhpRendererFactory($core->runtime()->rootPath(), $auth, $core->admin(), $core->sites()))
            ->create([dirname(__DIR__) . '/templates']);
        $core->admin()->navigation('mechaniki', 'Механики · MVP', '/admin/mechaniki', 'modules.manage', 95, 'layout');
        $handler = function (Request $request, array $variables = []) use ($core, $db, $auth, $view): Response {
            unset($variables);
            if ($auth->user() === null) {
                return Response::redirect('/admin/login');
            }
            foreach (['modules.manage', 'structure.manage', 'documents.manage', 'layouts.manage'] as $permission) {
                if (!$auth->can($permission)) {
                    return Response::html('<h1>403 Forbidden</h1>', 403);
                }
            }
            $error = null;
            if ($request->method === 'POST') {
                $token = $request->post['_csrf'] ?? null;
                if (!Csrf::validate(is_string($token) ? $token : null)) {
                    return Response::html('<h1>419 CSRF token mismatch</h1>', 419);
                }
                try {
                    $this->import($core, (int) $auth->user()['id']);
                    $core->delivery()->invalidate(['site:' . $core->sites()->adminId()]);
                    return Response::redirect('/admin/mechaniki?created=1');
                } catch (Throwable $exception) {
                    $error = $exception->getMessage();
                }
            }
            return Response::html($view->render('admin/mechaniki.php', [
                'error' => $error, 'created' => isset($request->query['created']),
            ]), $error === null ? 200 : 422);
        };
        $core->routes()->get('/admin/mechaniki', 'mechaniki.index', $handler);
        $core->routes()->post('/admin/mechaniki/import', 'mechaniki.import', $handler);
    }

    private function import(Core $core, int $userId): void
    {
        $db = $core->runtime()->database();
        $siteId = $core->sites()->adminId();
        $structure = new StructureRepository($db, $siteId);
        $db->beginTransaction();
        try {
            $lock = $db->prepare('SELECT pg_advisory_xact_lock(hashtext(:key))');
            $lock->execute(['key' => 'mechaniki_import_' . $siteId]);
            $rootId = null;
            foreach ($structure->all() as $node) {
                if ($node['path'] === '/mechaniki' || str_starts_with($node['path'], '/mechaniki/')) {
                    throw new RuntimeException('Раздел /mechaniki уже существует. Повторная загрузка не изменяет существующие страницы.');
                }
                if ($node['path'] === '/') {
                    $rootId = (int) $node['id'];
                }
            }
            if ($rootId === null) {
                throw new RuntimeException('Сначала создайте корневой узел сайта в «Структуре».');
            }
            $layouts = new LayoutRepository($db, $siteId);
            $layout = $layouts->findByCode('mechaniki');
            $layoutId = $layout !== null
                ? (int) $layout['id']
                : $layouts->create('Механики · MVP', 'layouts/mechaniki.php', 'Пилотный сайт автосервиса.');
            $provision = new StructurePageProvisioningService($db, $structure, new PageSelectionRepository($db), $core->pages());
            $documents = new DocumentRepository($db);
            $sanitizer = new HtmlSanitizer();
            $pages = require dirname(__DIR__) . '/content/pages.php';
            $parents = ['root' => $rootId];
            foreach ($pages as $key => $page) {
                $nodeId = $provision->create([
                    'parent_id' => $parents[$page['parent']], 'layout_id' => $layoutId,
                    'name' => $page['name'], 'slug' => $page['slug'], 'title' => $page['title'],
                    'meta_description' => $page['description'], 'status' => 'published',
                    'is_active' => true, 'sorting' => count($parents) * 10, 'publish_at' => null,
                ], 'documents.page');
                $parents[$key] = $nodeId;
                $document = $documents->findActiveByCode($siteId, 'node-' . $nodeId);
                if ($document === null) {
                    throw new RuntimeException('Документ страницы не создан.');
                }
                $content = file_get_contents(dirname(__DIR__) . '/content/' . $key . '.html');
                if ($content === false) {
                    throw new RuntimeException('Файл содержимого не найден.');
                }
                $documentId = (int) $document['id'];
                $versionId = $documents->createDraftVersion($documentId, $sanitizer->rich($content));
                $documents->publishVersion($documentId, $versionId);
            }
            (new AuditLogRepository($db, $core->sites()->adminId()))->record($userId, 'mechaniki.import', 'site', $siteId, ['nodes' => $parents], null);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }
}
