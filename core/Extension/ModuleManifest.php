<?php

declare(strict_types=1);

namespace Core\Extension;

use RuntimeException;

final readonly class ModuleManifest
{
    /**
     * @param array<string, string> $requires
     * @param list<string> $purgeScripts
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $version,
        public string $extensionApi,
        public bool $defaultEnabled,
        public array $requires,
        public string $provider,
        public string $directory,
        public array $purgeScripts = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, string $directory): self
    {
        $code = trim((string) ($data['code'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        $version = trim((string) ($data['version'] ?? ''));
        $extensionApi = trim((string) ($data['extension_api'] ?? ''));
        $defaultEnabled = (bool) ($data['default_enabled'] ?? false);
        $provider = trim((string) ($data['provider'] ?? ''));
        $requires = $data['requires'] ?? [];
        $purge = $data['purge'] ?? [];

        if (!preg_match('/^[a-z][a-z0-9._-]{0,79}$/', $code)) {
            throw new RuntimeException(sprintf('Invalid module code in %s.', $directory));
        }
        if ($name === '' || preg_match_all('/./u', $name) > 120) {
            throw new RuntimeException(sprintf('Invalid module name for %s.', $code));
        }
        VersionConstraint::assertVersion($version);
        try {
            VersionConstraint::assertConstraint($extensionApi);
        } catch (RuntimeException $exception) {
            throw new RuntimeException(sprintf('Invalid extension_api for module %s.', $code), 0, $exception);
        }
        if ($provider === '') {
            throw new RuntimeException(sprintf('Module %s must declare a provider class.', $code));
        }
        if (!is_array($requires)) {
            throw new RuntimeException(sprintf('Module %s requires must be an object/map.', $code));
        }

        $normalizedRequires = [];
        foreach ($requires as $dependency => $constraint) {
            $dependency = trim((string) $dependency);
            $constraint = trim((string) $constraint);
            if (!preg_match('/^(?:core|[a-z][a-z0-9._-]{0,79})$/', $dependency) || $constraint === '') {
                throw new RuntimeException(sprintf('Invalid dependency declaration in module %s.', $code));
            }
            if ($dependency === $code) {
                throw new RuntimeException(sprintf('Module %s cannot depend on itself.', $code));
            }
            try {
                VersionConstraint::assertConstraint($constraint);
            } catch (RuntimeException $exception) {
                throw new RuntimeException(sprintf(
                    'Invalid dependency constraint for %s in module %s.',
                    $dependency,
                    $code,
                ), 0, $exception);
            }
            $normalizedRequires[$dependency] = $constraint;
        }
        ksort($normalizedRequires, SORT_STRING);

        if (!is_array($purge) || !array_is_list($purge)) {
            throw new RuntimeException(sprintf('Module %s purge must be a list of SQL paths.', $code));
        }

        $purgeScripts = [];
        foreach ($purge as $script) {
            $script = trim((string) $script);
            if ($script === ''
                || str_contains($script, "\0")
                || str_contains($script, '\\')
                || str_starts_with($script, '/')
                || preg_match('/^[A-Za-z]:/', $script) === 1
                || !str_ends_with(strtolower($script), '.sql')) {
                throw new RuntimeException(sprintf('Invalid purge SQL path in module %s.', $code));
            }

            $parts = explode('/', $script);
            foreach ($parts as $part) {
                if ($part === '' || $part === '.' || $part === '..') {
                    throw new RuntimeException(sprintf('Unsafe purge SQL path in module %s.', $code));
                }
            }

            if (isset($purgeScripts[$script])) {
                throw new RuntimeException(sprintf('Duplicate purge SQL path in module %s.', $code));
            }
            $purgeScripts[$script] = $script;
        }

        return new self(
            $code,
            $name,
            $version,
            $extensionApi,
            $defaultEnabled,
            $normalizedRequires,
            $provider,
            $directory,
            array_values($purgeScripts),
        );
    }
}
