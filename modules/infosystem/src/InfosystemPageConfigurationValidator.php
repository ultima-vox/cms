<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem;

use Core\Page\PageConfigurationValidatorInterface;
use RuntimeException;

final readonly class InfosystemPageConfigurationValidator implements PageConfigurationValidatorInterface
{
    public function validate(array $configuration): array
    {
        $allowed = ['view', 'limit'];
        foreach (array_keys($configuration) as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                throw new RuntimeException(sprintf('Unknown Infosystem page configuration key: %s.', (string) $key));
            }
        }

        $normalized = [];

        if (array_key_exists('view', $configuration)) {
            if (!is_string($configuration['view'])) {
                throw new RuntimeException('Infosystem page view must be a string.');
            }

            $view = strtolower(trim($configuration['view']));
            if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,127}$/', $view)) {
                throw new RuntimeException('Infosystem page view code is invalid.');
            }
            $normalized['view'] = $view;
        }

        if (array_key_exists('limit', $configuration)) {
            $limit = $configuration['limit'];
            if (is_string($limit) && ctype_digit($limit)) {
                $limit = (int) $limit;
            }
            if (!is_int($limit) || $limit < 1 || $limit > 500) {
                throw new RuntimeException('Infosystem page limit must be in the range 1..500.');
            }
            $normalized['limit'] = $limit;
        }

        return $normalized;
    }
}
