<?php

declare(strict_types=1);

use Core\View\PhpTemplateLinter;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = sys_get_temp_dir() . '/uvcms-template-lint-' . bin2hex(random_bytes(6));
$templates = $root . '/templates/layouts';
$moduleTemplates = $root . '/modules/demo/templates';
$moduleSource = $root . '/modules/demo/src';

foreach ([$templates, $moduleTemplates, $moduleSource] as $directory) {
    if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create template-linter smoke directory.');
    }
}

$valid = $templates . '/valid.php';
$invalid = $templates . '/invalid.php';
$moduleTemplate = $moduleTemplates . '/list.php';
$moduleSourceFile = $moduleSource . '/Broken.php';

try {
    file_put_contents($valid, '<main><?= text($title) ?></main>');

    $checked = (new PhpTemplateLinter($root . '/templates'))->lint();
    if ($checked !== ['layouts/valid.php']) {
        throw new RuntimeException('PHP template linter returned an unexpected template list.');
    }

    file_put_contents($moduleTemplate, '<section><?= text($title) ?></section>');
    file_put_contents($moduleSourceFile, '<?php if (true) {');

    $moduleChecked = (new PhpTemplateLinter($root . '/modules'))->lint();
    if ($moduleChecked !== ['demo/templates/list.php']) {
        throw new RuntimeException('PHP template linter did not isolate module template directories.');
    }

    file_put_contents($invalid, '<?php if (true) { ?>broken');

    try {
        (new PhpTemplateLinter($root . '/templates'))->lint();
        throw new RuntimeException('PHP template linter accepted invalid PHP syntax.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'PHP template linter accepted invalid PHP syntax.') {
            throw $exception;
        }

        if (!str_contains($exception->getMessage(), 'layouts/invalid.php')) {
            throw new RuntimeException('PHP template linter error does not identify the invalid template.');
        }
    }
} finally {
    @unlink($valid);
    @unlink($invalid);
    @unlink($moduleTemplate);
    @unlink($moduleSourceFile);
    @rmdir($templates);
    @rmdir($root . '/templates');
    @rmdir($moduleTemplates);
    @rmdir($moduleSource);
    @rmdir($root . '/modules/demo');
    @rmdir($root . '/modules');
    @rmdir($root);
}

fwrite(STDOUT, "TEMPLATE LINTER OK\n");
