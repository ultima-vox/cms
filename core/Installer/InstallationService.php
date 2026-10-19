<?php

declare(strict_types=1);

namespace Core\Installer;

use Core\Bootstrap\BuiltinExtensions;
use Core\Extension\Api\RuntimeApi;
use Core\Extension\Core;
use Core\Extension\ModuleLoader;
use Core\Extension\ModuleManager;
use Core\Extension\ModuleMigrationCoordinator;
use Core\MigrationRunner;
use Core\Repository\PageSelectionRepository;
use Core\Repository\SiteRepository;
use Core\Repository\StructureRepository;
use Core\Site\SiteContext;
use Core\Site\SiteResolver;
use Core\Structure\StructurePageProvisioningService;
use Dotenv\Dotenv;
use RuntimeException;
use Throwable;

final readonly class InstallationService
{
    public function __construct(private string $rootPath)
    {
    }

    /** @param array<string,mixed> $input */
    public function install(DatabaseConfig $config, array $input): void
    {
        $value = static function (string $key) use ($input): string {
            if (!is_string($input[$key] ?? null)) {
                throw new RuntimeException('Заполните все поля установки.');
            }
            return $input[$key];
        };
        $name = trim($value('site_name'));
        $email = trim($value('email'));
        $password = $value('admin_password');
        $url = rtrim(trim($value('site_url')), '/');
        $parts = parse_url($url);
        if ($name === '' || mb_strlen($name) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($email) > 320 || strlen($password) < 12 || strlen($password) > 72
            || $password !== $value('admin_password_confirm')) {
            throw new RuntimeException('Проверьте название, email и совпадающие пароли длиной 12–72 байта.');
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) || !is_array($parts)
            || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || isset($parts['user'], $parts['pass']) || isset($parts['user'])
            || isset($parts['query']) || isset($parts['fragment']) || !empty($parts['path'])) {
            throw new RuntimeException('Укажите полный адрес сайта без подпапки, параметров и логина.');
        }
        $checker = new EnvironmentChecker($this->rootPath);
        if (!$checker->requiredPassed($checker->checks())) {
            throw new RuntimeException('Сервер не прошёл обязательную проверку окружения.');
        }
        $environment = [
            'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_URL' => $url,
            'DB_DRIVER' => $config->driver, 'DB_HOST' => $config->host, 'DB_PORT' => (string) $config->port,
            'DB_NAME' => $config->database, 'DB_USER' => $config->user, 'DB_PASS' => $config->password,
        ];
        $env = '';
        foreach ($environment as $key => $content) {
            $escaped = str_replace(['\\', '"', '$', "\r", "\n"], ['\\\\', '\\"', '\\$', '\\r', '\\n'], $content);
            $env .= $key . '="' . $escaped . '"' . "\n";
        }
        if (Dotenv::parse($env) !== $environment) {
            throw new RuntimeException('Параметры подключения содержат неподдерживаемые символы.');
        }
        $handle = fopen($this->rootPath . '/storage/installation.lock', 'c');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Другая установка уже выполняется.');
        }
        $temporary = $this->rootPath . '/.env.install-' . bin2hex(random_bytes(8));
        $db = null;
        try {
            if (file_exists($this->rootPath . '/.env') || file_exists($this->rootPath . '/storage/install.lock')) {
                throw new RuntimeException('CMS уже настроена; повторная установка запрещена.');
            }
            $file = fopen($temporary, 'x');
            if ($file === false) {
                throw new RuntimeException('Не удалось подготовить файл конфигурации.');
            }
            try {
                if (!chmod($temporary, 0600) || fwrite($file, $env) !== strlen($env)) {
                    throw new RuntimeException('Не удалось безопасно записать конфигурацию.');
                }
            } finally {
                fclose($file);
            }
            $db = (new DatabaseConnector())->connect($config);
            if ($config->driver === 'pgsql' && (int) $db->query('SHOW server_version_num')->fetchColumn() < 160000) {
                throw new RuntimeException('Требуется PostgreSQL 16 или новее.');
            }
            if ($config->driver === 'mysql') {
                $version = (string) $db->query('SELECT VERSION()')->fetchColumn();
                $maria = stripos($version, 'MariaDB') !== false;
                if (!preg_match('/([0-9]+\.[0-9]+\.[0-9]+)/', $version, $match)
                    || version_compare($match[1], $maria ? '10.11.0' : '8.0.21', '<')) {
                    throw new RuntimeException('Legacy: требуется MySQL 8.0.21+ или MariaDB 10.11+.');
                }
            }
            $host = (new SiteResolver(new SiteRepository($db), $url))->normalizeHost((string) ($parts['host'] ?? ''));
            if ($host === null) {
                throw new RuntimeException('Некорректный домен сайта.');
            }
            \Core\Database\SqlDialect::lock($db, 'ultima_vox_cms_install');
            $objects = (int) $db->query(\Core\Database\SqlDialect::mysql($db)
                ? 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()'
                : "SELECT COUNT(*) FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = current_schema() AND c.relkind IN ('r','p','v','m','S')")->fetchColumn();
            if ($objects !== 0) {
                throw new RuntimeException('Нужна отдельная пустая база данных. Существующие данные не изменены.');
            }
            (new MigrationRunner($db, $this->rootPath . '/database/migrations'))->migrate();
            (new ModuleMigrationCoordinator($db, $this->rootPath))->migrateEnabled();
            (new ModuleManager($db, $this->rootPath))->sync();
            $db->beginTransaction();
            $statement = $db->prepare('UPDATE sites SET name = :name WHERE code = :code');
            $statement->execute(['name' => $name, 'code' => 'default']);
            $statement = $db->prepare('INSERT INTO site_domains (site_id, host, is_primary) VALUES (1, :host, TRUE)');
            $statement->execute(['host' => $host]);
            $statement = $db->prepare('INSERT INTO users (email, password_hash, display_name) VALUES (:email, :hash, :name) ' . \Core\Database\SqlDialect::returningId($db));
            $statement->execute(['email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT), 'name' => $name]);
            $userId = \Core\Database\SqlDialect::insertedId($db, $statement);
            $statement = $db->prepare("INSERT INTO user_roles (user_id, role_id) SELECT :id, id FROM roles WHERE code = 'superadmin'");
            $statement->execute(['id' => $userId]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('Роль администратора не создана.');
            }
            $core = new Core(new RuntimeApi($db, $this->rootPath, new SiteContext(1, 'default', $name, $host)));
            (new BuiltinExtensions())->register($core);
            (new ModuleLoader($this->rootPath))->load($core);
            $core->freeze();
            (new StructurePageProvisioningService(
                $db, new StructureRepository($db, 1), new PageSelectionRepository($db), $core->pages(),
            ))->create([
                'parent_id' => null, 'layout_id' => null, 'name' => 'Главная', 'slug' => '',
                'title' => $name, 'meta_description' => null, 'status' => 'published',
                'is_active' => true, 'sorting' => 0, 'publish_at' => null,
            ]);
            $db->commit();
            if (!rename($temporary, $this->rootPath . '/.env')) {
                throw new RuntimeException('База готова, но .env не сохранён. Обратитесь к администратору хостинга.');
            }
            if (file_put_contents($this->rootPath . '/storage/install.lock', gmdate('c') . "\n", LOCK_EX) === false) {
                throw new RuntimeException('CMS настроена, но маркер установки не записан. Можно перейти к /admin/login.');
            }
        } catch (Throwable $exception) {
            if ($db !== null && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
            if ($db !== null) {
                \Core\Database\SqlDialect::unlock($db, 'ultima_vox_cms_install');
            }
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
