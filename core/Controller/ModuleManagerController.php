<?php

declare(strict_types=1);

namespace Core\Controller;

use Core\Extension\ModuleLoader;
use Core\Extension\ModuleManager;
use Core\Extension\ModuleMigrationRunner;
use Core\Extension\ModulePackageLifecycle;
use Core\Extension\ModulePackagePurger;
use Core\Extension\ModulePackageUploadService;
use Core\Http\Request;
use Core\Http\Response;
use Core\Repository\AuditLogRepository;
use Core\Security\AuthService;
use Core\Security\Csrf;
use Core\View\AdminPhpRenderer;
use PDO;
use Throwable;

final readonly class ModuleManagerController
{
    public function __construct(
        private AuthService $auth,
        private AdminPhpRenderer $view,
        private AuditLogRepository $audit,
        private PDO $db,
        private string $rootPath,
    ) {
    }

    /** @param array<string, string> $variables */
    public function index(Request $request, array $variables = []): Response
    {
        unset($variables);
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }

        $manager = new ModuleManager($this->db, $this->rootPath);
        $stateRows = [];
        foreach ($manager->listing() as $row) {
            $stateRows[$row['code']] = $row;
        }

        $inventoryRows = [];
        foreach ((new ModulePackageLifecycle($this->db, $this->rootPath))->inventory() as $row) {
            $inventoryRows[$row['module_code']] = $row;
        }

        $modules = [];
        foreach ((new ModuleLoader($this->rootPath))->discover() as $manifest) {
            $state = $stateRows[$manifest->code] ?? null;
            $inventory = $inventoryRows[$manifest->code] ?? null;
            $modules[] = [
                'code' => $manifest->code,
                'name' => $manifest->name,
                'version' => $manifest->version,
                'extension_api' => $manifest->extensionApi,
                'enabled' => is_array($state) ? (bool) $state['enabled'] : $manifest->defaultEnabled,
                'synchronized' => is_array($state) && (bool) $state['synchronized'],
                'package_managed' => is_array($inventory) && ($inventory['source'] ?? null) === 'package',
                'purge_available' => $manifest->purgeScripts !== [],
                'requires' => $manifest->requires,
            ];
        }

        return Response::html($this->view->render('admin/modules/index.php', [
            'csrf_token' => Csrf::token(),
            'modules' => $modules,
            'notice' => $this->notice($request),
        ]));
    }

    /** @param array<string, string> $variables */
    public function install(Request $request, array $variables = []): Response
    {
        unset($variables);
        if (($denied = $this->requireMutation($request)) !== null) {
            return $denied;
        }
        $package = $request->file('package');
        if ($package === null) {
            return Response::html('<h1>ZIP-пакет не выбран.</h1><p><a href="/admin/modules">Вернуться к модулям</a></p>', 422);
        }

        try {
            $lifecycle = new ModulePackageLifecycle($this->db, $this->rootPath);
            $manifest = (new ModulePackageUploadService($lifecycle, $this->rootPath))->install($package, $request->file('signature'));
            $this->audit($request, 'module.install', $manifest->code, [
                'version' => $manifest->version,
                'signed_upload' => $request->file('signature') !== null,
            ]);
            return Response::redirect('/admin/modules?installed=' . rawurlencode($manifest->code));
        } catch (Throwable $exception) {
            return $this->error($exception, 422);
        }
    }

    /** @param array<string, string> $variables */
    public function updatePackage(Request $request, array $variables = []): Response
    {
        unset($variables);
        if (($denied = $this->requireMutation($request)) !== null) {
            return $denied;
        }
        $package = $request->file('package');
        if ($package === null) {
            return Response::html('<h1>ZIP-пакет обновления не выбран.</h1><p><a href="/admin/modules">Вернуться к модулям</a></p>', 422);
        }

        try {
            $lifecycle = new ModulePackageLifecycle($this->db, $this->rootPath);
            $manifest = (new ModulePackageUploadService($lifecycle, $this->rootPath))->update($package, $request->file('signature'));
            $this->audit($request, 'module.update', $manifest->code, [
                'version' => $manifest->version,
                'signed_upload' => $request->file('signature') !== null,
            ]);
            return Response::redirect('/admin/modules?updated=' . rawurlencode($manifest->code));
        } catch (Throwable $exception) {
            return $this->error($exception, 422);
        }
    }

    /** @param array<string, string> $variables */
    public function purgeForm(Request $request, array $variables = []): Response
    {
        unset($request);
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }
        $code = $this->moduleCode($variables);
        if ($code === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }

        try {
            $manifest = (new ModuleManager($this->db, $this->rootPath))->requireManifest($code);
            if ($manifest->purgeScripts === []) {
                return Response::html('<h1>Purge не поддерживается этим модулем.</h1><p><a href="/admin/modules">Вернуться к модулям</a></p>', 409);
            }

            return Response::html($this->view->render('admin/modules/purge.php', [
                'csrf_token' => Csrf::token(),
                'module' => [
                    'code' => $manifest->code,
                    'name' => $manifest->name,
                    'version' => $manifest->version,
                    'purge_scripts' => $manifest->purgeScripts,
                ],
            ]));
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    /** @param array<string, string> $variables */
    public function purge(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requireMutation($request)) !== null) {
            return $denied;
        }
        $code = $this->moduleCode($variables);
        if ($code === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }
        $confirmation = is_string($request->post['confirmation'] ?? null)
            ? trim($request->post['confirmation'])
            : '';

        try {
            (new ModulePackagePurger($this->db, $this->rootPath))->purge($code, $confirmation);
            $this->audit($request, 'module.purge', $code, ['data_destroyed' => true]);
            return Response::redirect('/admin/modules?purged=' . rawurlencode($code));
        } catch (Throwable $exception) {
            return $this->error($exception, 422);
        }
    }

    /** @param array<string, string> $variables */
    public function sync(Request $request, array $variables = []): Response
    {
        unset($variables);
        if (($denied = $this->requireMutation($request)) !== null) {
            return $denied;
        }
        try {
            $result = (new ModuleManager($this->db, $this->rootPath))->sync();
            $this->audit($request, 'module.sync', null, $result);
            return Response::redirect('/admin/modules?synced=1');
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    /** @param array<string, string> $variables */
    public function enable(Request $request, array $variables = []): Response
    {
        return $this->toggle($request, $variables, true);
    }

    /** @param array<string, string> $variables */
    public function disable(Request $request, array $variables = []): Response
    {
        return $this->toggle($request, $variables, false);
    }

    /** @param array<string, string> $variables */
    public function migrate(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requireMutation($request)) !== null) {
            return $denied;
        }
        $code = $this->moduleCode($variables);
        if ($code === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }
        try {
            $applied = (new ModuleMigrationRunner($this->db, $this->rootPath))->migrate($code);
            $this->audit($request, 'module.migrate', $code, ['applied' => $applied]);
            return Response::redirect('/admin/modules?migrated=' . rawurlencode($code));
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    /** @param array<string, string> $variables */
    public function remove(Request $request, array $variables = []): Response
    {
        if (($denied = $this->requireMutation($request)) !== null) {
            return $denied;
        }
        $code = $this->moduleCode($variables);
        if ($code === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }
        try {
            (new ModulePackageLifecycle($this->db, $this->rootPath))->remove($code);
            $this->audit($request, 'module.remove', $code, ['data_preserved' => true]);
            return Response::redirect('/admin/modules?removed=' . rawurlencode($code));
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    /** @param array<string, string> $variables */
    private function toggle(Request $request, array $variables, bool $enabled): Response
    {
        if (($denied = $this->requireMutation($request)) !== null) {
            return $denied;
        }
        $code = $this->moduleCode($variables);
        if ($code === null) {
            return Response::html('<h1>404 Not Found</h1>', 404);
        }
        try {
            $manager = new ModuleManager($this->db, $this->rootPath);
            $enabled ? $manager->enable($code) : $manager->disable($code);
            $this->audit($request, $enabled ? 'module.enable' : 'module.disable', $code, []);
            return Response::redirect('/admin/modules?' . ($enabled ? 'enabled=' : 'disabled=') . rawurlencode($code));
        } catch (Throwable $exception) {
            return $this->error($exception);
        }
    }

    private function requirePermission(): ?Response
    {
        if ($this->auth->user() === null) {
            return Response::redirect('/admin/login');
        }
        if (!$this->auth->can('modules.manage')) {
            return Response::html('<h1>403 Forbidden</h1>', 403);
        }
        return null;
    }

    private function requireMutation(Request $request): ?Response
    {
        if (($denied = $this->requirePermission()) !== null) {
            return $denied;
        }
        if (!Csrf::validate(is_string($request->post['_csrf'] ?? null) ? $request->post['_csrf'] : null)) {
            return Response::html('<h1>419 CSRF token mismatch</h1>', 419);
        }
        return null;
    }

    /** @param array<string, string> $variables */
    private function moduleCode(array $variables): ?string
    {
        $code = strtolower(trim((string) ($variables['code'] ?? '')));
        return preg_match('/^[a-z][a-z0-9._-]{0,79}$/', $code) ? $code : null;
    }

    private function notice(Request $request): ?string
    {
        if (isset($request->query['synced'])) {
            return 'Реестр модулей синхронизирован.';
        }
        foreach ([
            'installed' => 'пакет загружен и установлен в выключенном состоянии',
            'updated' => 'код пакета обновлён; перед включением примените миграции',
            'enabled' => 'включён',
            'disabled' => 'выключен',
            'migrated' => 'миграции применены',
            'removed' => 'код пакета удалён, данные сохранены',
            'purged' => 'пакет и объявленные модулем данные необратимо удалены',
        ] as $key => $message) {
            $code = $request->query[$key] ?? null;
            if (is_string($code) && $code !== '') {
                return sprintf('Модуль %s: %s.', $code, $message);
            }
        }
        return null;
    }

    private function error(Throwable $exception, int $status = 409): Response
    {
        return Response::html(
            '<h1>Операция не выполнена</h1><p>' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') . '</p><p><a href="/admin/modules">Вернуться к модулям</a></p>',
            $status,
        );
    }

    /** @param array<string, mixed> $context */
    private function audit(Request $request, string $action, ?string $moduleCode, array $context): void
    {
        $user = $this->auth->user();
        $ip = $request->server['REMOTE_ADDR'] ?? null;
        if ($moduleCode !== null) {
            $context['module_code'] = $moduleCode;
        }
        $this->audit->record(
            is_array($user) ? (int) $user['id'] : null,
            $action,
            'module',
            null,
            $context,
            is_string($ip) ? $ip : null,
        );
    }
}
