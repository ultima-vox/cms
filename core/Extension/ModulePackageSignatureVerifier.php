<?php

declare(strict_types=1);

namespace Core\Extension;

use RuntimeException;

final readonly class ModulePackageSignatureVerifier
{
    private const ALGORITHM = 'ed25519-sha256-v1';
    private const MAX_SIGNATURE_FILE_SIZE = 16_384;
    private const DOMAIN = "ultima-vox-module-package:v1\n";

    public function __construct(private string $rootPath)
    {
    }

    /** @return array{signed:bool,key_id:?string,algorithm:?string} */
    public function verify(string $archivePath): array
    {
        $signaturePath = $archivePath . '.sig';
        $required = $this->signedPackagesRequired();

        if (!is_file($signaturePath)) {
            if ($required) {
                throw new RuntimeException('Signed module packages are required, but signature sidecar is missing.');
            }

            return ['signed' => false, 'key_id' => null, 'algorithm' => null];
        }

        if (!extension_loaded('sodium') || !function_exists('sodium_crypto_sign_verify_detached')) {
            throw new RuntimeException('Signed module package verification requires the PHP sodium extension.');
        }
        if (!is_readable($signaturePath)) {
            throw new RuntimeException('Module package signature sidecar is not readable.');
        }

        $size = filesize($signaturePath);
        if (!is_int($size) || $size < 2 || $size > self::MAX_SIGNATURE_FILE_SIZE) {
            throw new RuntimeException('Module package signature sidecar has invalid size.');
        }

        $raw = file_get_contents($signaturePath);
        if (!is_string($raw)) {
            throw new RuntimeException('Unable to read module package signature sidecar.');
        }

        try {
            $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Module package signature sidecar is not valid JSON.', 0, $exception);
        }
        if (!is_array($data)) {
            throw new RuntimeException('Module package signature sidecar must contain a JSON object.');
        }

        $algorithm = trim((string) ($data['algorithm'] ?? ''));
        $keyId = trim((string) ($data['key_id'] ?? ''));
        $encodedSignature = trim((string) ($data['signature'] ?? ''));

        if ($algorithm !== self::ALGORITHM) {
            throw new RuntimeException(sprintf('Unsupported module package signature algorithm: %s.', $algorithm));
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,119}$/', $keyId)) {
            throw new RuntimeException('Invalid module package signature key_id.');
        }

        $trustedKey = $this->trustedKeys()[$keyId] ?? null;
        if (!is_string($trustedKey)) {
            throw new RuntimeException(sprintf('Module package was signed by untrusted key: %s.', $keyId));
        }

        $publicKey = base64_decode($trustedKey, true);
        $signature = base64_decode($encodedSignature, true);
        if (!is_string($publicKey)
            || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || !is_string($signature)
            || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            throw new RuntimeException('Module package signature or trusted public key has invalid encoding.');
        }

        $digest = hash_file('sha256', $archivePath, true);
        if (!is_string($digest)) {
            throw new RuntimeException('Unable to hash module package for signature verification.');
        }
        $message = self::DOMAIN . $digest;

        if (!sodium_crypto_sign_verify_detached($signature, $message, $publicKey)) {
            throw new RuntimeException('Module package signature verification failed.');
        }

        return ['signed' => true, 'key_id' => $keyId, 'algorithm' => $algorithm];
    }

    /** @return array<string, string> */
    private function trustedKeys(): array
    {
        $path = $this->rootPath . '/config/module-trust.php';
        if (!is_file($path)) {
            return [];
        }

        $config = require $path;
        if (!is_array($config)) {
            throw new RuntimeException('config/module-trust.php must return an array.');
        }

        $keys = $config['keys'] ?? [];
        if (!is_array($keys)) {
            throw new RuntimeException('Module trust configuration keys must be an array.');
        }

        $trusted = [];
        foreach ($keys as $keyId => $publicKey) {
            $keyId = trim((string) $keyId);
            $publicKey = trim((string) $publicKey);
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,119}$/', $keyId) || $publicKey === '') {
                throw new RuntimeException('Module trust configuration contains an invalid key entry.');
            }
            $trusted[$keyId] = $publicKey;
        }

        return $trusted;
    }

    private function signedPackagesRequired(): bool
    {
        $value = $_ENV['CMS_REQUIRE_SIGNED_MODULES']
            ?? $_SERVER['CMS_REQUIRE_SIGNED_MODULES']
            ?? getenv('CMS_REQUIRE_SIGNED_MODULES');

        if ($value === false || $value === null || trim((string) $value) === '') {
            return false;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
