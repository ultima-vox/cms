<?php

declare(strict_types=1);

namespace Core\View;

use ParseError;
use PhpToken;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class PhpTemplateLinter
{
    public function __construct(private readonly string $templatesPath)
    {
    }

    /** @return list<string> */
    public function lint(): array
    {
        if (!is_dir($this->templatesPath)) {
            throw new RuntimeException('Каталог шаблонов не найден.');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->templatesPath, RecursiveDirectoryIterator::SKIP_DOTS),
        );
        $checked = [];
        $moduleRoot = basename(rtrim($this->templatesPath, DIRECTORY_SEPARATOR)) === 'modules';

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with(strtolower($file->getFilename()), '.php')) {
                continue;
            }

            $relative = ltrim(str_replace($this->templatesPath, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);

            if ($moduleRoot && preg_match('#^[^/]+/templates/#', $relative) !== 1) {
                continue;
            }

            if (str_ends_with(strtolower($file->getFilename()), '.html.php')) {
                throw new RuntimeException(
                    sprintf('Устаревший суффикс шаблона .html.php: %s. Используйте обычный .php.', $relative),
                );
            }

            $source = file_get_contents($file->getPathname());
            if ($source === false) {
                throw new RuntimeException('Не удалось прочитать PHP-шаблон: ' . $file->getPathname());
            }

            try {
                PhpToken::tokenize($source, TOKEN_PARSE);
            } catch (ParseError $exception) {
                throw new RuntimeException(
                    sprintf(
                        'Ошибка PHP в шаблоне %s: %s (строка %d).',
                        $relative,
                        $exception->getMessage(),
                        $exception->getLine(),
                    ),
                    0,
                    $exception,
                );
            }

            $checked[] = $relative;
        }

        sort($checked, SORT_STRING);

        return $checked;
    }
}
