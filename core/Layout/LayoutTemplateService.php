<?php

declare(strict_types=1);

namespace Core\Layout;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;
use Twig\Source;

final class LayoutTemplateService
{
    private string $runtimeLayoutsPath;
    private string $packagedLayoutsPath;
    private string $twigCachePath;

    public function __construct(private readonly string $rootPath)
    {
        $this->runtimeLayoutsPath = $rootPath . '/storage/templates/layouts';
        $this->packagedLayoutsPath = $rootPath . '/templates/layouts';
        $this->twigCachePath = $rootPath . '/storage/cache/twig';
    }

    public function templatePathForCode(string $code): string
    {
        $code = strtolower(trim($code));

        if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/', $code)) {
            throw new RuntimeException('Код макета: только a-z, 0-9, дефис и подчёркивание, до 80 символов.');
        }

        return 'layouts/' . $code . '.twig';
    }

    public function validate(string $source, string $templateName = 'layout.twig'): void
    {
        if (trim($source) === '') {
            throw new RuntimeException('Шаблон макета не может быть пустым.');
        }

        $twig = new Environment(new ArrayLoader());

        try {
            $twig->parse($twig->tokenize(new Source($source, $templateName)));
        } catch (SyntaxError $exception) {
            throw new RuntimeException(
                sprintf('Ошибка Twig: %s (строка %d).', $exception->getRawMessage(), $exception->getTemplateLine()),
                0,
                $exception,
            );
        }
    }

    public function read(string $templatePath): string
    {
        $runtimeFile = $this->runtimeFile($templatePath);
        $packagedFile = $this->packagedFile($templatePath);
        $file = is_file($runtimeFile) ? $runtimeFile : $packagedFile;
        $content = @file_get_contents($file);

        if ($content === false) {
            throw new RuntimeException('Не удалось прочитать файл макета.');
        }

        return $content;
    }

    public function hasOverride(string $templatePath): bool
    {
        return is_file($this->runtimeFile($templatePath));
    }

    public function create(string $templatePath, string $source): void
    {
        $runtimeFile = $this->runtimeFile($templatePath);
        $packagedFile = $this->packagedFile($templatePath);

        if (is_file($runtimeFile) || is_file($packagedFile)) {
            throw new RuntimeException('Файл макета с таким кодом уже существует.');
        }

        $this->validate($source, $templatePath);
        $this->writeAtomically($runtimeFile, $source);
        $this->clearTwigCache();
    }

    public function update(string $templatePath, string $source): void
    {
        $runtimeFile = $this->runtimeFile($templatePath);

        if (!is_file($runtimeFile) && !is_file($this->packagedFile($templatePath))) {
            throw new RuntimeException('Файл макета не найден.');
        }

        $this->validate($source, $templatePath);
        $this->writeAtomically($runtimeFile, $source);
        $this->clearTwigCache();
    }

    public function resetOverride(string $templatePath): void
    {
        $runtimeFile = $this->runtimeFile($templatePath);

        if (!is_file($this->packagedFile($templatePath))) {
            throw new RuntimeException('Штатный файл макета отсутствует.');
        }

        if (is_file($runtimeFile) && !unlink($runtimeFile)) {
            throw new RuntimeException('Не удалось удалить runtime-override макета.');
        }

        $this->clearTwigCache();
    }

    public function delete(string $templatePath): void
    {
        $runtimeFile = $this->runtimeFile($templatePath);

        if (is_file($runtimeFile) && !unlink($runtimeFile)) {
            throw new RuntimeException('Не удалось удалить runtime-файл макета.');
        }

        $this->clearTwigCache();
    }

    private function runtimeFile(string $templatePath): string
    {
        $this->assertTemplatePath($templatePath);

        return $this->rootPath . '/storage/templates/' . $templatePath;
    }

    private function packagedFile(string $templatePath): string
    {
        $this->assertTemplatePath($templatePath);

        return $this->rootPath . '/templates/' . $templatePath;
    }

    private function assertTemplatePath(string $templatePath): void
    {
        if (!preg_match('#^layouts/[a-z0-9][a-z0-9_-]{0,79}\.twig$#', $templatePath)) {
            throw new RuntimeException('Некорректный путь файла макета.');
        }
    }

    private function writeAtomically(string $file, string $source): void
    {
        if (!is_dir($this->runtimeLayoutsPath)
            && !mkdir($this->runtimeLayoutsPath, 0775, true)
            && !is_dir($this->runtimeLayoutsPath)) {
            throw new RuntimeException('Не удалось создать runtime-каталог макетов.');
        }

        if (!is_writable($this->runtimeLayoutsPath)) {
            throw new RuntimeException('Каталог storage/templates/layouts недоступен для записи PHP-FPM.');
        }

        $temporary = tempnam($this->runtimeLayoutsPath, '.uv-layout-');
        if ($temporary === false) {
            throw new RuntimeException('Не удалось создать временный файл макета.');
        }

        try {
            if (file_put_contents($temporary, $source, LOCK_EX) === false) {
                throw new RuntimeException('Не удалось записать временный файл макета.');
            }

            chmod($temporary, 0664);

            if (!rename($temporary, $file)) {
                throw new RuntimeException('Не удалось атомарно сохранить файл макета.');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function clearTwigCache(): void
    {
        if (!is_dir($this->twigCachePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->twigCachePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();

            if ($item->isDir()) {
                if (!rmdir($path)) {
                    throw new RuntimeException('Макет сохранён, но не удалось очистить каталог Twig-кеша.');
                }
            } elseif (!unlink($path)) {
                throw new RuntimeException('Макет сохранён, но не удалось очистить Twig-кеш.');
            }
        }
    }
}
