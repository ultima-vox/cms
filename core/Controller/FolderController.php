<?php

declare(strict_types=1);
namespace Core\Controller;

use Core\Content\FolderRepository;
use Core\Extension\Core;
use Core\Http\Request;
use Core\Http\Response;
use Core\Repository\AuditLogRepository;
use Core\Security\AuthService;
use Core\Security\Csrf;
use Core\View\AdminPhpRenderer;
use Throwable;

final readonly class FolderController
{
    public function __construct(private Core $core, private AuthService $auth, private AuditLogRepository $audit, private AdminPhpRenderer $view) {}
    public function handle(Request $request, array $variables): Response
    {
        if ($this->auth->user() === null) return Response::redirect('/admin/login');
        $definition = $this->core->folders()->definition($variables['scope'] ?? '');
        if ($definition === null) return Response::html('<h1>404 Not Found</h1>', 404);
        if (!$this->auth->can($definition['permission'])) return Response::html('<h1>403 Forbidden</h1>', 403);
        $folders = new FolderRepository($this->core->runtime()->database(), $this->core->sites()->adminId(), $definition['scope']);
        $id = isset($variables['id']) ? (int) $variables['id'] : null;
        $folder = $id === null ? null : $folders->find($id);
        if ($id !== null && $folder === null) return Response::html('<h1>404 Not Found</h1>', 404);
        $error = null;
        if ($request->method === 'POST') {
            if (!Csrf::validate(is_string($request->post['_csrf'] ?? null) ? $request->post['_csrf'] : null)) return Response::html('<h1>419 CSRF token mismatch</h1>', 419);
            try {
                $delete = str_ends_with($request->path, '/delete');
                if ($delete) $folders->delete($id);
                else $id = $folders->save($id, is_string($request->post['name'] ?? null) ? $request->post['name'] : '', FolderRepository::id($request->post['parent_id'] ?? null));
                $this->audit->record((int) $this->auth->user()['id'], $delete ? 'folder.delete' : 'folder.save', 'folder', $id, ['scope' => $definition['scope'], 'site_id' => $this->core->sites()->adminId()], null);
                return Response::redirect($definition['path'] . ($delete ? '' : '?folder=' . $id));
            } catch (Throwable $e) { $error = $e->getMessage(); $folder = array_merge($folder ?? [], $request->post); }
        }
        return Response::html($this->view->render('admin/folders/form.php', ['definition' => $definition, 'folder' => $folder, 'folder_id' => $id, 'options' => $folders->all(), 'error' => $error]), $error === null ? 200 : 422);
    }
}
