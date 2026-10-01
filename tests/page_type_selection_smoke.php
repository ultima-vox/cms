<?php

declare(strict_types=1);

use Core\Page\PageTypeSelection;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$default = PageTypeSelection::fromNode([]);
if ($default->code !== PageTypeSelection::DEFAULT_CODE || $default->configuration !== []) {
    throw new RuntimeException('Default page type selection is invalid.');
}

$configured = PageTypeSelection::fromNode([
    'page_type' => 'Infosystem.List',
    'page_config' => '{"source":"news","limit":10}',
]);
if ($configured->code !== 'infosystem.list'
    || $configured->configuration !== ['source' => 'news', 'limit' => 10]) {
    throw new RuntimeException('Configured page type selection was not normalized.');
}

try {
    new PageTypeSelection('invalid type', []);
    throw new RuntimeException('Invalid page type code was accepted.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'Invalid page type code was accepted.') {
        throw $exception;
    }
}

try {
    PageTypeSelection::fromNode([
        'page_type' => 'demo',
        'page_config' => '{broken-json}',
    ]);
    throw new RuntimeException('Invalid page configuration JSON was accepted.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'Invalid page configuration JSON was accepted.') {
        throw $exception;
    }
}

fwrite(STDOUT, "PAGE TYPE SELECTION OK\n");
