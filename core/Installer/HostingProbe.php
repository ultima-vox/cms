<?php

declare(strict_types=1);

namespace Core\Installer;

use RuntimeException;

/** Browser installation gate: verify this public origin before creating any database objects. */
final readonly class HostingProbe
{
    public function __construct(private string $rootPath)
    {
    }

    public function verify(string $url): void
    {
        $target = $this->target($url);
        $release = json_decode((string) @file_get_contents($this->rootPath . '/release.json'), true);
        $webroot = ($release['layout'] ?? 'public') === 'webroot';
        $public = $webroot ? dirname($this->rootPath) : $this->rootPath . '/public';
        $name = 'cms-install-probe-' . bin2hex(random_bytes(16)) . '.txt';
        $proof = bin2hex(random_bytes(32));
        $file = $public . '/assets/' . $name;
        $handle = @fopen($file, 'x');
        if ($handle === false) {
            throw new RuntimeException('Проверка защиты хостинга: разрешите PHP временную запись в публичный каталог assets сайта.');
        }
        try {
            try {
                if (!chmod($file, 0644) || fwrite($handle, $proof) !== strlen($proof)) {
                    throw new RuntimeException('Не удалось записать контрольный файл проверки хостинга.');
                }
            } finally {
                fclose($handle);
            }
            [$status, $body] = $this->get($target, '/assets/' . $name);
            if ($status !== 200 || !hash_equals($proof, $body)) {
                throw new RuntimeException('Проверка защиты хостинга: URL не возвращает контрольный файл этой установки. Проверьте адрес, HTTPS, DNS и доступ сервера к собственному сайту. Перенаправления не допускаются; укажите конечный адрес.');
            }
            $paths = $webroot
                ? ['/app/composer.json', '/app/.env.example', '/app/public/index.php']
                : ['/composer.json', '/.env.example', '/vendor/autoload.php'];
            foreach ($paths as $path) {
                [$status] = $this->get($target, $path);
                if (!in_array($status, [403, 404], true)) {
                    throw new RuntimeException('Небезопасное размещение: приватный путь ' . $path . ' не закрыт ответом 403/404. Установка остановлена до изменения БД. Проверьте защиту каталога приложения средствами хостинга.');
                }
            }
        } finally {
            if (is_file($file) && !unlink($file)) {
                throw new RuntimeException('Не удалось удалить временный контрольный файл хостинга. Установка остановлена.');
            }
        }
    }

    /** @return array{scheme:string, host:string, authority:string, port:int, ip:string} */
    private function target(string $url): array
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || !in_array($parts['path'] ?? '', ['', '/'], true)) {
            throw new RuntimeException('Для проверки хостинга нужен полный HTTP(S) адрес без подпапки и параметров.');
        }
        $host = trim((string) ($parts['host'] ?? ''), '[]');
        if ($host === '' || !preg_match('/\A[a-zA-Z0-9.:-]+\z/D', $host)) {
            throw new RuntimeException('Недопустимый хост для проверки размещения.');
        }
        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (@gethostbynamel($host) ?: []);
        if ($ips === []) {
            throw new RuntimeException('Не удалось определить IPv4-адрес сайта для проверки хостинга.');
        }
        // Pin one validated address for all probes: no second DNS lookup or redirect can reach another host.
        $ip = $ips[0];
        foreach ($ips as $candidate) {
            // A private origin requires an exact, trusted process-level allowlist entry.
            // Never derive this exception from Host/SERVER_PORT/request headers.
            $localOrigin = getenv('INSTALLER_LOCAL_ORIGIN');
            $allowedLocal = is_string($localOrigin) && $localOrigin !== '' && rtrim($url, '/') === $localOrigin;
            $public = filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            // Also exclude shared-address space and IPv4-mapped IPv6 destinations.
            $shared = preg_match('/\A100\.(?:6[4-9]|[7-9][0-9]|1[01][0-9]|12[0-7])\./', $candidate)
                || str_starts_with(strtolower($candidate), '::ffff:');
            if (!$allowedLocal && (!$public || $shared)) {
                throw new RuntimeException('Проверка хостинга не обращается к внутренним адресам, без явного разрешения владельца сервера. Проверьте публичный DNS сайта.');
            }
        }
        $authority = (str_contains($host, ':') ? '[' . $host . ']' : $host) . ':' . $port;
        return ['scheme' => $parts['scheme'], 'host' => $host, 'authority' => $authority, 'port' => $port, 'ip' => $ip];
    }

    /** @param array{scheme:string, host:string, authority:string, port:int, ip:string} $target
     *  @return array{int, string}
     */
    private function get(array $target, string $path): array
    {
        $address = str_contains($target['ip'], ':') ? '[' . $target['ip'] . ']' : $target['ip'];
        $context = stream_context_create([
            'http' => ['method' => 'GET', 'follow_location' => 0, 'max_redirects' => 0,
                'timeout' => 3.0, 'ignore_errors' => true, 'protocol_version' => 1.1,
                'header' => ['Host: ' . $target['authority'], 'Connection: close', 'Accept-Encoding: identity', 'User-Agent: UltimaVox-Installer-Probe']],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $target['host'], 'SNI_enabled' => true],
        ]);
        $http_response_header = [];
        $body = @file_get_contents($target['scheme'] . '://' . $address . ':' . $target['port'] . $path, false, $context, 0, 4096);
        if ($body === false || !preg_match('~\AHTTP/\S+ ([0-9]{3})\b~', $http_response_header[0] ?? '', $match)) {
            throw new RuntimeException('Проверка защиты хостинга не получила ответ. Проверьте исходящие HTTP(S), сертификат и доступность сайта; установка не продолжена.');
        }
        return [(int) $match[1], $body];
    }
}
