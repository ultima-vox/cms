<?php

declare(strict_types=1);

namespace Core\Extension;

use RuntimeException;

final class VersionConstraint
{
    private const VERSION_PATTERN = '/^v?\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/';

    private function __construct()
    {
    }

    public static function assertVersion(string $version): void
    {
        if (preg_match(self::VERSION_PATTERN, trim($version)) !== 1) {
            throw new RuntimeException(sprintf('Invalid semantic version: %s.', $version));
        }
    }

    public static function assertConstraint(string $constraint): void
    {
        self::parse($constraint);
    }

    public static function matches(string $version, string $constraint): bool
    {
        self::assertVersion($version);
        foreach (self::parse($constraint) as $group) {
            $matches = true;
            foreach ($group as $predicate) {
                if (!self::matchesPredicate($version, $predicate)) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /** @return list<list<string>> */
    private static function parse(string $constraint): array
    {
        $constraint = trim($constraint);
        if ($constraint === '') {
            throw new RuntimeException('Version constraint cannot be empty.');
        }

        $groups = [];
        foreach (preg_split('/\s*\|\|\s*/', $constraint) ?: [] as $rawGroup) {
            $rawGroup = trim($rawGroup);
            if ($rawGroup === '') {
                throw new RuntimeException(sprintf('Invalid version constraint: %s.', $constraint));
            }

            $tokens = preg_split('/(?:\s+|\s*,\s*)/', $rawGroup, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($tokens === []) {
                throw new RuntimeException(sprintf('Invalid version constraint: %s.', $constraint));
            }

            foreach ($tokens as $token) {
                if (!self::isValidPredicate($token)) {
                    throw new RuntimeException(sprintf(
                        'Unsupported version constraint token "%s" in "%s".',
                        $token,
                        $constraint,
                    ));
                }
            }
            $groups[] = array_values($tokens);
        }

        return $groups;
    }

    private static function isValidPredicate(string $token): bool
    {
        if (in_array(strtolower($token), ['*', 'x'], true)) {
            return true;
        }

        return preg_match(
            '/^(?:\^|~|>=|<=|>|<|=)?v?\d+(?:\.\d+){0,2}(?:\.(?:\*|x|X))?$/',
            $token,
        ) === 1;
    }

    private static function matchesPredicate(string $version, string $predicate): bool
    {
        $predicate = trim($predicate);
        if (in_array(strtolower($predicate), ['*', 'x'], true)) {
            return true;
        }

        if (str_starts_with($predicate, '^')) {
            $parts = self::numericParts(substr($predicate, 1));
            return version_compare($version, self::formatVersion($parts), '>=')
                && version_compare($version, self::caretUpperBound($parts), '<');
        }

        if (str_starts_with($predicate, '~')) {
            $raw = substr($predicate, 1);
            $specifiedParts = count(explode('.', ltrim($raw, 'vV')));
            $parts = self::numericParts($raw);
            return version_compare($version, self::formatVersion($parts), '>=')
                && version_compare($version, self::tildeUpperBound($parts, $specifiedParts), '<');
        }

        if (preg_match('/^(>=|<=|>|<|=)(.+)$/', $predicate, $matches) === 1) {
            return version_compare(
                $version,
                self::formatVersion(self::numericParts($matches[2])),
                $matches[1],
            );
        }

        if (str_contains(strtolower($predicate), 'x') || str_contains($predicate, '*')) {
            return self::matchesWildcard($version, $predicate);
        }

        return version_compare($version, self::formatVersion(self::numericParts($predicate)), '==');
    }

    /** @return array{0:int,1:int,2:int} */
    private static function numericParts(string $version): array
    {
        $version = ltrim(trim($version), 'vV');
        if (preg_match('/^\d+(?:\.\d+){0,2}$/', $version) !== 1) {
            throw new RuntimeException(sprintf('Invalid version in constraint: %s.', $version));
        }

        $parts = array_map('intval', explode('.', $version));
        return [$parts[0] ?? 0, $parts[1] ?? 0, $parts[2] ?? 0];
    }

    /** @param array{0:int,1:int,2:int} $parts */
    private static function formatVersion(array $parts): string
    {
        return sprintf('%d.%d.%d', $parts[0], $parts[1], $parts[2]);
    }

    /** @param array{0:int,1:int,2:int} $parts */
    private static function caretUpperBound(array $parts): string
    {
        if ($parts[0] > 0) {
            return sprintf('%d.0.0', $parts[0] + 1);
        }
        if ($parts[1] > 0) {
            return sprintf('0.%d.0', $parts[1] + 1);
        }
        return sprintf('0.0.%d', $parts[2] + 1);
    }

    /** @param array{0:int,1:int,2:int} $parts */
    private static function tildeUpperBound(array $parts, int $specifiedParts): string
    {
        return $specifiedParts >= 3
            ? sprintf('%d.%d.0', $parts[0], $parts[1] + 1)
            : sprintf('%d.0.0', $parts[0] + 1);
    }

    private static function matchesWildcard(string $version, string $predicate): bool
    {
        $raw = ltrim(trim($predicate), '=vV');
        $parts = explode('.', $raw);
        $wildcardIndex = null;
        foreach ($parts as $index => $part) {
            if ($part === '*' || strtolower($part) === 'x') {
                $wildcardIndex = $index;
                break;
            }
        }

        if ($wildcardIndex === null || $wildcardIndex === 0 || $wildcardIndex > 2) {
            throw new RuntimeException(sprintf('Unsupported wildcard constraint: %s.', $predicate));
        }

        $numeric = array_map('intval', array_slice($parts, 0, $wildcardIndex));
        $lowerParts = [$numeric[0] ?? 0, $numeric[1] ?? 0, 0];
        $lower = self::formatVersion($lowerParts);
        $upper = $wildcardIndex === 1
            ? sprintf('%d.0.0', $lowerParts[0] + 1)
            : sprintf('%d.%d.0', $lowerParts[0], $lowerParts[1] + 1);

        return version_compare($version, $lower, '>=') && version_compare($version, $upper, '<');
    }
}
