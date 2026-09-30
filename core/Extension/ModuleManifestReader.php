<?php

declare(strict_types=1);

namespace Core\Extension;

use Core\Version;
use JsonException;
use RuntimeException;

final class ModuleManifestReader
{
    public function read(string $directory): ?ModuleManifest
    {
        $jsonPath = $directory . '/module.json';
        if (is_file($jsonPath)) {
            return $this->readJson($jsonPath, $directory);
        }

        $legacyPath = $directory . '/module.php';
        if (!is_file($legacyPath)) {
            return null;
        }

        $definition = require $legacyPath;
        if ($definition instanceof ModuleInterface) {
            $definition = [
                'code' => basename($directory),
                'name' => basename($directory),
                'version' => '0.0.0',
                'extension_api' => '^1.0',
                'default_enabled' => true,
                'requires' => ['core' => '>=' . Version::STRING],
                'provider' => $definition::class,
            ];
        }
        if (!is_array($definition)) {
            throw new RuntimeException(sprintf('Legacy module manifest %s must return an array.', $legacyPath));
        }

        return ModuleManifest::fromArray($definition, $directory);
    }

    public function readJson(string $manifestPath, string $directory): ModuleManifest
    {
        $json = file_get_contents($manifestPath);
        if ($json === false) {
            throw new RuntimeException('Unable to read module manifest: ' . $manifestPath);
        }

        try {
            $definition = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Invalid JSON module manifest: ' . $manifestPath, 0, $exception);
        }

        if (!is_array($definition) || array_is_list($definition)) {
            throw new RuntimeException('Module JSON manifest must contain an object: ' . $manifestPath);
        }

        return ModuleManifest::fromArray($definition, $directory);
    }
}
