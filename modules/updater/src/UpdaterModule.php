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
use RuntimeException;
use Throwable;

final readonly class UpdaterModule implements ModuleInterface
{
    public function register(Core $core): void
    {
        $core->permissions()->define('updates.view', 'Просмотр обновлений CMS', ['superadmin', 'admin']);
        $core->permissions()->define('updates.apply', 'Установка обновлений CMS', ['superadmin']);
        $db = $core->runtime()->database();
        $root = $core->runtime()->rootPath();
        $auth = new AuthService(new UserRepository($db), new LoginAttemptRepository($db));
        $view = (new AdminPhpRendererFactory($root, $auth, $core->admin(), $core->sites()))->create([dirname(__DIR__) . '/templates']);
        $updater = new FileUpdater($root, $db);
        $state = new UpdateState($root);
        $core->admin()->navigation('updates', 'Обновления', '/admin/updates', 'updates.view', 100, 'module');
        $handler = static function (Request $request, array $variables = []) use ($core, $db, $auth, $view, $updater, $state): Response {
            unset($variables);
            $user = $auth->user();
            if ($user === null) return Response::redirect('/admin/login');
            if (!$auth->can('updates.view') || ($request->path === '/admin/updates/apply' && !$auth->can('updates.apply'))) {
                return Response::html('<h1>403 Forbidden</h1>', 403);
            }
            $error = null;
            $current = null;
            $warning = $_SESSION['_cms_update_warning'] ?? null;
            unset($_SESSION['_cms_update_warning']);
            try {
                $current = $updater->current();
                if ($request->method === 'POST') {
                    $token = $request->post['_csrf'] ?? null;
                    if (!Csrf::validate(is_string($token) ? $token : null)) return Response::html('<h1>419 CSRF token mismatch</h1>', 419);
                    if ($request->path === '/admin/updates/check') {
                        $state->save((new ReleaseClient())->latest());
                        return Response::redirect('/admin/updates');
                    }
                    $offer = $state->offer()['release'] ?? null;
                    if ($offer === null || ($request->post['version'] ?? '') !== $offer['version']
                        || ($request->post['checksum'] ?? '') !== $offer['sha256']) {
                        throw new RuntimeException('Сначала проверьте обновления и ознакомьтесь со списком изменений. Проверка действует 24 часа.');
                    }
                    foreach ($state->readiness() as $check) {
                        if (!$check['ok']) throw new RuntimeException('Сервер не готов: ' . $check['label']);
                    }
                    @set_time_limit(180);
                    $client = new ReleaseClient();
                    $latest = $client->latest();
                    if ($latest['version'] !== $offer['version'] || $latest['sha256'] !== $offer['sha256']) {
                        throw new RuntimeException('Релиз изменился после проверки. Проверьте обновления ещё раз.');
                    }
                    $backup = $updater->apply($client->download($offer['url'], 80_000_000), $offer);
                    // Installation has committed. An audit/cache failure must not report it as rolled back.
                    try {
                        foreach ($core->sites()->active() as $site) $core->delivery()->invalidate(['site:' . $site->id]);
                        (new AuditLogRepository($db, $core->sites()->adminId()))->record((int) $user['id'], 'cms.update', 'release', null,
                            ['from' => $current['version'], 'to' => $offer['version'], 'backup' => basename($backup)], null);
                    } catch (Throwable $exception) {
                        error_log('CMS update post-install: ' . $exception->getMessage());
                        $_SESSION['_cms_update_warning'] = 'Версия установлена, но не завершено обновление кеша или журнала аудита. Проверьте журнал ошибок сервера.';
                    }
                    return Response::redirect('/admin/updates?updated=1');
                }
            } catch (Throwable $exception) { $error = $exception->getMessage(); }
            $stored = $state->offer();
            return Response::html($view->render('admin/updates.php', [
                'current' => $current, 'offer' => $stored['release'] ?? null, 'checkedAt' => $stored['checked_at'] ?? null,
                'error' => $error, 'warning' => $warning, 'updated' => isset($request->query['updated']),
                'canApply' => $auth->can('updates.apply'), 'checks' => $state->readiness(), 'history' => $state->history(),
            ]), $error === null ? 200 : 422);
        };
        $core->routes()->get('/admin/updates', 'updates.index', $handler);
        $core->routes()->post('/admin/updates/check', 'updates.check', $handler);
        $core->routes()->post('/admin/updates/apply', 'updates.apply', $handler);
    }
}
