<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Updater;

use Core\Extension\Core;
use Core\Extension\ModuleInterface;
use Core\Http\Request;
use Core\Http\Response;
use Core\Repository\AuditLogRepository;
use Core\Repository\LoginAttemptRepository;
use Core\Repository\UserRepository;
use Core\Security\AuthService;
use Core\Security\Csrf;
use Core\View\AdminPhpRendererFactory;
use Throwable;

final readonly class UpdaterModule implements ModuleInterface
{
    public function register(Core $core): void
    {
        $db = $core->runtime()->database();
        $auth = new AuthService(new UserRepository($db), new LoginAttemptRepository($db));
        $view = (new AdminPhpRendererFactory($core->runtime()->rootPath(), $auth, $core->admin(), $core->sites()))
            ->create([dirname(__DIR__) . '/templates']);
        $updater = new FileUpdater($core->runtime()->rootPath());
        $core->admin()->navigation('updates', 'Обновления', '/admin/updates', 'modules.manage', 100, 'module');
        $handler = static function (Request $request, array $variables = []) use ($core, $db, $auth, $view, $updater): Response {
            unset($variables);
            if ($auth->user() === null) return Response::redirect('/admin/login');
            if (!$auth->can('modules.manage')) return Response::html('<h1>403 Forbidden</h1>', 403);
            $error = null;
            $current = null;
            try {
                $current = $updater->current();
                if ($request->method === 'POST') {
                    $token = $request->post['_csrf'] ?? null;
                    if (!Csrf::validate(is_string($token) ? $token : null)) return Response::html('<h1>419 CSRF token mismatch</h1>', 419);
                    if ($request->path === '/admin/updates/check') {
                        $_SESSION['_cms_update_offer'] = (new ReleaseClient())->latest();
                        return Response::redirect('/admin/updates?checked=1');
                    }
                    $offer = $_SESSION['_cms_update_offer'] ?? null;
                    if (!is_array($offer) || ($request->post['version'] ?? '') !== $offer['version']) {
                        throw new \RuntimeException('Сначала проверьте обновления и ознакомьтесь со списком изменений.');
                    }
                    @set_time_limit(180);
                    $archive = (new ReleaseClient())->download($offer['url'], 80_000_000);
                    $backup = $updater->apply($archive, $offer);
                    unset($_SESSION['_cms_update_offer']);
                    // Code updates preserve the DB. Invalidate all active sites after installation.
                    foreach ($core->sites()->active() as $site) $core->delivery()->invalidate(['site:' . $site->id]);
                    (new AuditLogRepository($db))->record((int) $auth->user()['id'], 'cms.update', 'release', null,
                        ['from' => $current['version'], 'to' => $offer['version'], 'backup' => basename($backup)], null);
                    return Response::redirect('/admin/updates?updated=1');
                }
            } catch (Throwable $exception) { $error = $exception->getMessage(); }
            $offer = $_SESSION['_cms_update_offer'] ?? null;
            return Response::html($view->render('admin/updates.php', [
                'current' => $current, 'offer' => is_array($offer) ? $offer : null,
                'error' => $error, 'updated' => isset($request->query['updated']),
            ]), $error === null ? 200 : 422);
        };
        $core->routes()->get('/admin/updates', 'updates.index', $handler);
        $core->routes()->post('/admin/updates/check', 'updates.check', $handler);
        $core->routes()->post('/admin/updates/apply', 'updates.apply', $handler);
    }
}
