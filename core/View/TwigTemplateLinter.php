<?php

declare(strict_types=1);

namespace Core\View;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Source;

final class TwigTemplateLinter
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

        $twig = new Environment(new ArrayLoader());
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->templatesPath, RecursiveDirectoryIterator::SKIP_DOTS),
        );
        $checked = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'twig') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if ($source === false) {
                throw new RuntimeException('Не удалось прочитать Twig-шаблон: ' . $file->getPathname());
            }

            $relative = ltrim(str_replace($this->templatesPath, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            $twig->parse($twig->tokenize(new Source($source, $relative)));
            $checked[] = $relative;
        }

        sort($checked, SORT_STRING);
        return $checked;
    }
}
