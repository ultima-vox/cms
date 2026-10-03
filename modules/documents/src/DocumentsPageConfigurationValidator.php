<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Documents;

use Core\Page\PageConfigurationValidatorInterface;
use RuntimeException;

final readonly class DocumentsPageConfigurationValidator implements PageConfigurationValidatorInterface
{
    public function validate(array $configuration): array
    {
        foreach (array_keys($configuration) as $key) {
            if ($key !== 'document') {
                throw new RuntimeException(sprintf('Unknown Documents page configuration key: %s.', (string) $key));
            }
        }

        $document = $configuration['document'] ?? null;
        if (!is_string($document)) {
            throw new RuntimeException('Documents page configuration requires a document code.');
        }

        $document = strtolower(trim($document));
        if (!preg_match('/^[a-z][a-z0-9_-]{0,119}$/', $document)) {
            throw new RuntimeException('Documents page configuration contains an invalid document code.');
        }

        return ['document' => $document];
    }
}
