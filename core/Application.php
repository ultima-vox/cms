<?php

declare(strict_types=1);

namespace Core;

use Core\Bootstrap\BuiltinExtensions;
use Core\Bootstrap\BuiltinRoutes;
use Core\Content\HtmlSanitizer;
use Core\Content\HtmlSanitizingHandler;
use Core\Controller\AdminController;
use Core\Controller\AuthController;
use Core\Controller\HealthController;
use Core\Controller\LayoutController;
use Core\Controller\ModuleManagerController;
use Core\Controller\NodeController;
use Core\Controller\StructureController;
use Core\Delivery\DeliveryInvalidatingHandler;
use Core\Delivery\Cache\CacheBackendInterface;
use Core\Delivery\Page\PageCache;
use Core\Extension\Api\RuntimeApi;
use Core\Extension\Core as ExtensionCore;
use Core\Extension\ModuleLoader;
use Core\Http\Request;
use Core\Layout\LayoutHierarchyResolver;
use Core\Layout\LayoutTemplateService;
use Core\Page\PageRenderer;
use Core\Repository\AuditLogRepository;
use Core\Repository\LayoutRepository;
use Core\Repository\LoginAttemptRepository;
use Core\Repository\NodeRepository;
use Core\Repository\PageSelectionRepository;
use Core\Repository\SiteRepository;
use Core\Repository\StructureRepository;
use Core\Repository\UserRepository;
use Core\Routing\Permalink;
use Core\Security\AuthService;
use Core\Security\PermissionGate;
use Core\Security\SecurityHeaders;
use Core\Site\AdminSiteSelector;
use Core\Site\SiteContext;
use Core\Site\SiteResolver;
use Core\Structure\StructurePageProvisioningService;
use Core\View\AdminPhpRendererFactory;
use Core\View\PhpRenderer;
use RuntimeException;
use Throwable;

final class Application
{
    private const SESSION_COOKIE = 'uvcms_session';

    public function __construct(
        private readonly string $rootPath,
        private readonly ?CacheBackendInterface $cacheBackend = null,
    )
    {
    }

    public function run(Request $request): void
    {
        $this->startSessionForRequest($request);

        $db = Database::connection();
        (new \Core\Extension\PendingExtensionSync($db, $this->rootPath))->run();
        $siteRepository = new SiteRepository($db);
        $auth = new AuthService(new UserRepository($db), new LoginAttemptRepository($db));
        $access = new \Core\Site\SiteAccessRepository($db);
        $user = $this->isAdminPath($request->path) ? $auth->user() : null;
        if ($user !== null && $access->filter($siteRepository->allActive(), (int) $user['id']) === []) {
            if ($request->path === '/admin/logout' && $request->method === 'POST' && \Core\Security\Csrf::validate(is_string($request->post['_csrf'] ?? null) ? $request->post['_csrf'] : null)) {
                $auth->logout();
                \Core\Http\Response::redirect('/admin/login', 303)->send();
                return;
            }
            SecurityHeaders::apply(\Core\Http\Response::html('<h1>403</h1><p>Нет доступа к активным сайтам. Обратитесь к суперадминистратору.</p><form method="post" action="/admin/logout"><input type="hidden" name="_csrf" value="' . htmlspecialchars(\Core\Security\Csrf::token(), ENT_QUOTES, 'UTF-8') . '"><button type="submit">Выйти</button></form>', 403))->send();
            return;
        }
        $adminSite = $this->isAdminPath($request->path)
            ? (new AdminSiteSelector($siteRepository, $access, $user !== null ? (int) $user['id'] : null))->current()
            : new SiteContext(1, 'default', 'Default site', '');
        if ($user !== null && $request->method === 'POST' && isset($request->post['_site_id'])
            && (string) $request->post['_site_id'] !== (string) $adminSite->id) {
            SecurityHeaders::apply(\Core\Http\Response::html('<h1>409</h1><p>В другой вкладке изменён текущий сайт. Перезагрузите форму перед сохранением.</p>', 409))->send();
            return;
        }
        $layoutRepository = new LayoutRepository($db, $adminSite->id);
        $core = new ExtensionCore(new RuntimeApi($db, $this->rootPath, $adminSite, $this->cacheBackend));
        (new BuiltinExtensions())->register($core);

        $auth = new AuthService(
            new UserRepository($db),
            new LoginAttemptRepository($db),
        );
        $adminView = (new AdminPhpRendererFactory(
            $this->rootPath,
            $auth,
            $core->admin(),
            $core->sites(),
        ))->create();
        $php = new PhpRenderer($this->rootPath, $core);

        $audit = new AuditLogRepository($db, $adminSite->id);
        $permissionGate = new PermissionGate($auth);
        $html = new HtmlSanitizingHandler(new HtmlSanitizer());
        $siteResolver = new SiteResolver(
            $siteRepository,
            Config::string('APP_URL', 'http://localhost'),
        );
        $structureRepository = new StructureRepository($db, $core->sites()->adminId());
        $structureProvisioning = new StructurePageProvisioningService(
            $db,
            $structureRepository,
            new PageSelectionRepository($db),
            $core->pages(),
        );

        $builtinRoutes = new BuiltinRoutes(
            new AuthController($auth, $adminView),
            new AdminController($auth, $adminView, $core->admin(), $core->sites(), $audit),
            new StructureController(
                $auth,
                $structureRepository,
                $structureProvisioning,
                $core->pages(),
                $audit,
                $adminView,
            ),
            new LayoutController(
                $auth,
                $layoutRepository,
                new LayoutTemplateService($this->rootPath, $adminSite->id, $adminSite->setting('legacy_layout_overrides', false) === true),
                $audit,
                $adminView,
                new \Core\Content\FolderRepository($db, $adminSite->id, 'layouts'),
            ),
            new ModuleManagerController(
                $auth,
                $adminView,
                $audit,
                $db,
                $this->rootPath,
            ),
            new HealthController($db),
            new NodeController(
                $siteResolver,
                $php,
                new PageRenderer(
                    new NodeRepository($db),
                    $php,
                    $core->pages(),
                    new LayoutHierarchyResolver($layoutRepository),
                ),
                new PageCache($core->cache()),
                $core->delivery(),
                $auth,
            ),
            $permissionGate,
            $html,
            new DeliveryInvalidatingHandler($core->delivery()),
            $core->sites()->adminId(),
        );

        $builtinRoutes->register($core->routes());
        $folderController = new \Core\Controller\FolderController($core, $auth, $audit, $adminView);
        $core->routes()->post('/admin/folders/{scope:[a-z][a-z0-9._-]*}', 'folders.store', [$folderController, 'handle']);
        $core->routes()->get('/admin/folders/{scope:[a-z][a-z0-9._-]*}/{id:\\d+}/edit', 'folders.edit', [$folderController, 'handle']);
        $core->routes()->post('/admin/folders/{scope:[a-z][a-z0-9._-]*}/{id:\\d+}', 'folders.update', [$folderController, 'handle']);
        $core->routes()->post('/admin/folders/{scope:[a-z][a-z0-9._-]*}/{id:\\d+}/delete', 'folders.delete', [$folderController, 'handle']);
        (new ModuleLoader($this->rootPath))->load($core);
        $builtinRoutes->registerPublicFallback($core->routes());
        $core->freeze();

        $router = new Router($core->routes(), $this->rootPath);
        $response = SecurityHeaders::apply($router->dispatch($request));

        if ($this->isHttps()) {
            $response = $response->withHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }

        $response->send();
    }

    /** Generate one anonymous snapshot; this does not enable HTTP static serving. */
    public function publishStatic(string $siteCode, string $path, ?string $style = null): string
    {
        $permalink = new Permalink($path, $style ?? 'clean');
        $db = Database::connection();
        // Match public HTTP boot; capture the fence before site/module/content reads.
        $core = new ExtensionCore(new RuntimeApi(
            $db,
            $this->rootPath,
            new SiteContext(1, 'default', 'Default site', ''),
            $this->cacheBackend,
        ));
        $generation = $core->delivery()->beginStaticGeneration();
        $sites = new SiteRepository($db);
        $record = $sites->findActiveByCode($siteCode);
        if ($record === null) {
            throw new RuntimeException('Unknown or inactive site.');
        }
        $resolver = new SiteResolver($sites, Config::string('APP_URL', 'http://localhost'));
        $host = is_string($record['host'] ?? null) ? $resolver->normalizeHost($record['host']) : null;
        if ($host === null) {
            throw new RuntimeException('Static generation requires a primary domain for the site.');
        }
        $request = new Request(
            'GET',
            $permalink->nodePath,
            $permalink->nodePath,
            [],
            [],
            ['HTTP_HOST' => $host],
            [],
        );
        $site = $resolver->resolve($request);
        if ($site === null || $site->id !== (int) $record['id']) {
            throw new RuntimeException('Primary domain does not resolve to the requested site.');
        }
        $style ??= $site->staticPermalinkStyle();
        $permalink = new Permalink($path, $style);
        if (session_status() === PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Static generation requires a session-free CLI process.');
        }

        (new BuiltinExtensions())->register($core);
        (new ModuleLoader($this->rootPath))->load($core);
        $core->freeze();

        try {
            if (!$core->delivery()->pageCacheAllowed($request, $site, false)) {
                throw new RuntimeException('Static generation was vetoed by the request cache policy.');
            }
            $renderer = new PageRenderer(
                new NodeRepository($db),
                new PhpRenderer($this->rootPath, $core),
                $core->pages(),
                new LayoutHierarchyResolver(new LayoutRepository($db)),
            );
            $result = $renderer->render($request, $site);
            if ($result === null) {
                throw new RuntimeException('Published page was not found.');
            }
            return $core->delivery()->publishRenderedStatic($site->id, $permalink->nodePath, $result, $generation, $style);
        } catch (Throwable $exception) {
            $core->delivery()->deleteStatic($site->id, $permalink->nodePath, $style, $generation['token']);
            throw $exception;
        }
    }

    private function startSessionForRequest(Request $request): void
    {
        if (!$this->isAdminPath($request->path)
            && !array_key_exists(self::SESSION_COOKIE, $request->cookies)) {
            return;
        }

        $this->startSession();
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        session_name(self::SESSION_COOKIE);
        session_set_cookie_params([
            'httponly' => true,
            'secure' => $this->isHttps(),
            'samesite' => 'Lax',
            'path' => '/',
        ]);
        session_start();
    }

    private function isAdminPath(string $path): bool
    {
        return $path === '/admin' || str_starts_with($path, '/admin/');
    }

    private function isHttps(): bool
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }
}
