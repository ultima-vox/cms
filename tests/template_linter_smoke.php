<?php

declare(strict_types=1);

use Core\View\PhpTemplateLinter;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = sys_get_temp_dir() . '/uvcms-template-lint-' . bin2hex(random_bytes(6));
$templates = $root . '/templates/layouts';

if (!mkdir($templates, 0775, true) && !is_dir($templates)) {
    throw new RuntimeException('Unable to create template-linter smoke directory.');
}

$valid = $templates . '/valid.html.php';
$invalid = $templates . '/invalid.html.php';

try {
    file_put_contents($valid, '<main><?= text($title) ?></main>');

    $checked = (new PhpTemplateLinter($root . '/templates'))->lint();
    if ($checked !== ['layouts/valid.html.php']) {
        throw new RuntimeException('PHP template linter returned an unexpected template list.');
    }

    file_put_contents($invalid, '<?php if (true) { ?>broken');

    try {
        (new PhpTemplateLinter($root . '/templates'))->lint();
        throw new RuntimeException('PHP template linter accepted invalid PHP syntax.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'PHP template linter accepted invalid PHP syntax.') {
            throw $exception;
        }

        if (!str_contains($exception->getMessage(), 'layouts/invalid.html.php')) {
            throw new RuntimeException('PHP template linter error does not identify the invalid template.');
        }
    }
} finally {
    @unlink($valid);
    @unlink($invalid);
    @rmdir($templates);
    @rmdir($root . '/templates');
    @rmdir($root);
}

fwrite(STDOUT, "TEMPLATE LINTER OK\n");
