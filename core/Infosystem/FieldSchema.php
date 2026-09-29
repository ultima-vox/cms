<?php

declare(strict_types=1);

namespace Core\Infosystem;

use RuntimeException;

final class FieldSchema
{
    private const TYPES = ['text', 'textarea', 'number', 'boolean', 'select', 'date'];

    /** @return list<array<string, mixed>> */
    public function decode(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }

        $schema = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($schema) || !array_is_list($schema)) {
            throw new RuntimeException('Схема полей должна быть JSON-массивом.');
        }

        $result = [];
        $codes = [];
        foreach ($schema as $index => $field) {
            if (!is_array($field)) {
                throw new RuntimeException(sprintf('Поле #%d имеет некорректный формат.', $index + 1));
            }

            $code = strtolower(trim((string) ($field['code'] ?? '')));
            $name = trim((string) ($field['name'] ?? ''));
            $type = strtolower(trim((string) ($field['type'] ?? 'text')));

            if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $code)) {
                throw new RuntimeException(sprintf('Некорректный код поля #%d.', $index + 1));
            }
            if ($name === '' || mb_strlen($name) > 120) {
                throw new RuntimeException(sprintf('Некорректное название поля %s.', $code));
            }
            if (!in_array($type, self::TYPES, true)) {
                throw new RuntimeException(sprintf('Поле %s имеет неподдерживаемый тип.', $code));
            }
            if (isset($codes[$code])) {
                throw new RuntimeException(sprintf('Код поля %s повторяется.', $code));
            }
            $codes[$code] = true;

            $options = [];
            if ($type === 'select') {
                $rawOptions = $field['options'] ?? [];
                if (is_string($rawOptions)) {
                    $rawOptions = explode(',', $rawOptions);
                }
                if (!is_array($rawOptions) || !array_is_list($rawOptions)) {
                    throw new RuntimeException(sprintf('Опции поля %s должны быть списком.', $code));
                }
                foreach ($rawOptions as $option) {
                    $value = trim((string) $option);
                    if ($value !== '' && !in_array($value, $options, true)) {
                        $options[] = $value;
                    }
                }
                if ($options === []) {
                    throw new RuntimeException(sprintf('Для select-поля %s нужна хотя бы одна опция.', $code));
                }
            }

            $result[] = [
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'required' => (bool) ($field['required'] ?? false),
                'filterable' => (bool) ($field['filterable'] ?? false),
                'options' => $options,
            ];
        }

        return $result;
    }

    /** @param list<array<string, mixed>> $schema
     *  @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function normalizeProperties(array $schema, array $input): array
    {
        $properties = [];

        foreach ($schema as $field) {
            $code = (string) $field['code'];
            $type = (string) $field['type'];
            $raw = $input[$code] ?? null;

            if ($type === 'boolean') {
                $value = $raw === '1' || $raw === 1 || $raw === true || $raw === 'on';
            } elseif ($type === 'number') {
                if ($raw === null || $raw === '') {
                    $value = null;
                } elseif (!is_numeric($raw)) {
                    throw new RuntimeException(sprintf('Поле «%s» должно быть числом.', $field['name']));
                } else {
                    $value = (float) $raw;
                }
            } else {
                $value = $raw === null ? null : trim((string) $raw);
                if ($value === '') {
                    $value = null;
                }
            }

            if (($field['required'] ?? false) && $value === null) {
                throw new RuntimeException(sprintf('Поле «%s» обязательно.', $field['name']));
            }
            if ($type === 'select' && $value !== null && !in_array($value, $field['options'], true)) {
                throw new RuntimeException(sprintf('Некорректное значение поля «%s».', $field['name']));
            }
            if ($type === 'date' && $value !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                throw new RuntimeException(sprintf('Некорректная дата в поле «%s».', $field['name']));
            }

            if ($value !== null) {
                $properties[$code] = $value;
            }
        }

        return $properties;
    }
}
