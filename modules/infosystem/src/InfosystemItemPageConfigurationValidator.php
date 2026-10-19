<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem;

use Core\Page\PageConfigurationValidatorInterface;
use RuntimeException;

final readonly class InfosystemItemPageConfigurationValidator implements PageConfigurationValidatorInterface
{
    public function validate(array $configuration): array
    {
        foreach (array_keys($configuration) as $key) {
            if (!is_string($key) || !in_array($key, ['item_path', 'view'], true)) {
                throw new RuntimeException('Unknown Infosystem item configuration key.');
            }
        }

        $path = $configuration['item_path'] ?? null;
        if (!is_string($path)
            || strlen($path) > 255
            || preg_match('#^/[a-z0-9-]+(?:/[a-z0-9-]+)*$#D', $path) !== 1) {
            throw new RuntimeException('Infosystem item path must be a normalized relative path.');
        }

        $result = ['item_path' => $path];
        if (array_key_exists('view', $configuration)) {
            $view = $configuration['view'];
            if (!is_string($view) || !preg_match('/^[a-z0-9][a-z0-9._-]{0,127}$/D', $view)) {
                throw new RuntimeException('Infosystem item view code is invalid.');
            }

            $result['view'] = $view;
        }

        return $result;
    }
}
