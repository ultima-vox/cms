<?php

declare(strict_types=1);

use Core\Content\HtmlSanitizer;
use UltimaVox\Modules\Infosystem\Admin\LegacyRichContentGuard;

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';
require_once $root . '/modules/infosystem/autoload.php';

$sanitizer = new HtmlSanitizer();
$guard = new LegacyRichContentGuard($sanitizer);
$complex = '<section class="service"><svg><use href="#icon"></use></svg><p>Saved service</p></section>';
$description = '<p>Editable lead</p>';
$safe = $sanitizer->rich($complex);
if ($safe === $complex) {
    throw new RuntimeException('Fixture must contain markup the WYSIWYG sanitizer cannot preserve.');
}
$existing = ['content' => $complex, 'description' => $description];
$form = [
    '_original_content_hash' => hash('sha256', $complex),
    '_original_description_hash' => hash('sha256', $description),
    'content' => $safe,
    'description' => $description,
];
$result = $guard->reconcile($existing, $form, [
    'content' => $safe,
    'description' => $description,
    'name' => 'Edited SEO metadata',
]);
if ($result['content'] !== $complex || $result['name'] !== 'Edited SEO metadata') {
    throw new RuntimeException('Metadata-only edit destroyed trusted imported content.');
}

foreach ([
    ['post' => array_replace($form, ['content' => '<p>Altered visual content</p>']),
        'data' => ['content' => '<p>Altered visual content</p>', 'description' => $description]],
    ['post' => array_replace($form, ['_original_content_hash' => hash('sha256', 'old text')]),
        'data' => ['content' => $safe, 'description' => $description]],
    ['post' => ['content' => $safe, 'description' => $description],
        'data' => ['content' => $safe, 'description' => $description]],
] as $case) {
    try {
        $guard->reconcile($existing, $case['post'], $case['data']);
        throw new RuntimeException('Unsafe rich-content form was accepted.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'Unsafe rich-content form was accepted.') {
            throw $exception;
        }
    }
}
$normal = '<p>Normal <strong>content</strong></p>';
$accepted = $guard->reconcile(
    ['content' => $normal, 'description' => null],
    ['content' => '<p>Revised</p>'],
    ['content' => '<p>Revised</p>'],
);
if ($accepted['content'] !== '<p>Revised</p>') {
    throw new RuntimeException('Standard WYSIWYG content update became read-only.');
}

echo "INFOSYSTEM LEGACY RICH HTML PRESERVATION OK\n";
