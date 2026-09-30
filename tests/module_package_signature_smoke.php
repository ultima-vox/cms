<?php

declare(strict_types=1);

use Core\Extension\ModulePackageSignatureVerifier;

require dirname(__DIR__) . '/vendor/autoload.php';

if (!extension_loaded('sodium') || !function_exists('sodium_crypto_sign_detached')) {
    fwrite(STDOUT, "MODULE PACKAGE SIGNATURE SKIPPED (sodium extension unavailable)\n");
    return;
}

$root = sys_get_temp_dir() . '/uvcms-package-signature-' . bin2hex(random_bytes(6));
if (!mkdir($root . '/config', 0775, true) && !is_dir($root . '/config')) {
    throw new RuntimeException('Unable to create package signature smoke root.');
}

$archive = $root . '/signed-module.zip';
file_put_contents($archive, "test package bytes\n", LOCK_EX);

$keypair = sodium_crypto_sign_keypair();
$secret = sodium_crypto_sign_secretkey($keypair);
$public = sodium_crypto_sign_publickey($keypair);
$keyId = 'test-key-2026';
$publicEncoded = base64_encode($public);

$config = "<?php\n\nreturn ['keys' => ['" . $keyId . "' => '" . $publicEncoded . "']];\n";
file_put_contents($root . '/config/module-trust.php', $config, LOCK_EX);

$digest = hash_file('sha256', $archive, true);
if (!is_string($digest)) {
    throw new RuntimeException('Unable to hash signature smoke package.');
}
$message = "ultima-vox-module-package:v1\n" . $digest;
$signature = sodium_crypto_sign_detached($message, $secret);
$sidecar = [
    'algorithm' => 'ed25519-sha256-v1',
    'key_id' => $keyId,
    'signature' => base64_encode($signature),
];
file_put_contents($archive . '.sig', json_encode($sidecar, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), LOCK_EX);

$verifier = new ModulePackageSignatureVerifier($root);
$result = $verifier->verify($archive);
if (!$result['signed'] || $result['key_id'] !== $keyId || $result['algorithm'] !== 'ed25519-sha256-v1') {
    throw new RuntimeException('Valid signed package was not recognized correctly.');
}

file_put_contents($archive, "tampered package bytes\n", LOCK_EX);
try {
    $verifier->verify($archive);
    throw new RuntimeException('Tampered signed package was accepted.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'Tampered signed package was accepted.') {
        throw $exception;
    }
}

file_put_contents($archive, "test package bytes\n", LOCK_EX);
$sidecar['key_id'] = 'unknown-key';
file_put_contents($archive . '.sig', json_encode($sidecar, JSON_THROW_ON_ERROR), LOCK_EX);
try {
    $verifier->verify($archive);
    throw new RuntimeException('Package signed by unknown key was accepted.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'Package signed by unknown key was accepted.') {
        throw $exception;
    }
}

@unlink($archive . '.sig');
$previous = $_ENV['CMS_REQUIRE_SIGNED_MODULES'] ?? null;
$_ENV['CMS_REQUIRE_SIGNED_MODULES'] = '1';
try {
    try {
        $verifier->verify($archive);
        throw new RuntimeException('Unsigned package was accepted in strict signature mode.');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'Unsigned package was accepted in strict signature mode.') {
            throw $exception;
        }
    }
} finally {
    if ($previous === null) {
        unset($_ENV['CMS_REQUIRE_SIGNED_MODULES']);
    } else {
        $_ENV['CMS_REQUIRE_SIGNED_MODULES'] = $previous;
    }
}

@unlink($archive);
@unlink($root . '/config/module-trust.php');
@rmdir($root . '/config');
@rmdir($root);

fwrite(STDOUT, "MODULE PACKAGE SIGNATURE OK\n");
