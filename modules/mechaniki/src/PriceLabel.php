<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Mechaniki;

use UltimaVox\Modules\Infosystem\MoneyValue;

final class PriceLabel
{
    /** @param array<string, mixed> $properties */
    public static function fromProperties(array $properties): string
    {
        if (isset($properties['amount_minor']) && ($properties['currency'] ?? 'RUB') === 'RUB') {
            try {
                return (!empty($properties['is_from']) ? 'от ' : '')
                    . MoneyValue::format($properties['amount_minor']) . ' ₽';
            } catch (\InvalidArgumentException) {
                // Preserve a custom label for historical data that needs correction.
            }
        }
        return (string) ($properties['price_label'] ?? 'По запросу');
    }
}
