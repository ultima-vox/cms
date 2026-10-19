<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem\Admin;

use Core\Content\HtmlSanitizer;
use RuntimeException;

/**
 * Preserve imported trusted HTML when an editor submits a different field.
 * Legacy markup must never silently be degraded by the rich-text sanitizer.
 */
final readonly class LegacyRichContentGuard
{
    public function __construct(private HtmlSanitizer $sanitizer)
    {
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $submitted
     * @param array<string, mixed> $normalized
     * @return array<string, mixed>
     */
    public function reconcile(array $existing, array $submitted, array $normalized): array
    {
        foreach (['content', 'description'] as $field) {
            $original = (string) ($existing[$field] ?? '');
            if ($original === '') {
                continue;
            }
            $safe = $this->sanitizer->rich($original);
            if ($safe === $original) {
                continue;
            }

            $hash = $submitted['_original_' . $field . '_hash'] ?? null;
            if (!is_string($hash) || !hash_equals(hash('sha256', $original), $hash)) {
                throw new RuntimeException(
                    'Элемент содержит сложную разметку. Страница изменена или форма устарела. Обновите страницу перед сохранением.'
                );
            }
            if ((string) ($normalized[$field] ?? '') !== $safe) {
                throw new RuntimeException(
                    'Сложную HTML-разметку нельзя безопасно преобразовать в визуальном редакторе. '
                    . 'Для изменения этого поля используйте специализированный шаблон. '
                    . 'Другие поля можно редактировать без потери контента.'
                );
            }

            $normalized[$field] = $original;
        }

        return $normalized;
    }
}
