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

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with(strtolower($file->getFilename()), '.html.php')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if ($source === false) {
                throw new RuntimeException('Не удалось прочитать PHP/HTML-шаблон: ' . $file->getPathname());
            }

            $relative = ltrim(str_replace($this->templatesPath, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);

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
