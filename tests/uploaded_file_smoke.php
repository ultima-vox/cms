<?php

declare(strict_types=1);

use Core\Http\Request;
use Core\Http\UploadedFile;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$package = UploadedFile::fromPhpFile([
    'name' => 'reviews.zip',
    'tmp_name' => '/tmp/php-upload-smoke',
    'size' => '1234',
    'error' => UPLOAD_ERR_OK,
]);

if (!$package instanceof UploadedFile
    || $package->clientName !== 'reviews.zip'
    || $package->size !== 1234
    || !$package->successful()) {
    throw new RuntimeException('UploadedFile normalization failed.');
}

$emptyOptional = UploadedFile::fromPhpFile([
    'name' => '',
    'tmp_name' => '',
    'size' => 0,
    'error' => UPLOAD_ERR_NO_FILE,
]);
if ($emptyOptional !== null) {
    throw new RuntimeException('UPLOAD_ERR_NO_FILE must normalize to null.');
}

$legacyRequest = new Request(
    method: 'GET',
    uri: '/',
    path: '/',
    query: [],
    post: [],
    server: [],
    cookies: [],
);
if ($legacyRequest->files !== [] || $legacyRequest->file('package') !== null) {
    throw new RuntimeException('Request file defaults are not backward-compatible.');
}

$request = new Request(
    method: 'POST',
    uri: '/admin/modules/install',
    path: '/admin/modules/install',
    query: [],
    post: [],
    server: [],
    cookies: [],
    files: ['package' => $package],
);
if ($request->file('package') !== $package || $request->file('signature') !== null) {
    throw new RuntimeException('Request::file() failed.');
}

fwrite(STDOUT, "UPLOADED FILE OK\n");
