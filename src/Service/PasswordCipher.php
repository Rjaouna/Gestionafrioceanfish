<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PasswordCipher
{
    private string $key;

    public function __construct(
        #[Autowire('%env(APP_VAULT_KEY)%')]
        string $encodedKey,
    ) {
        $key = base64_decode($encodedKey, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \InvalidArgumentException('APP_VAULT_KEY must be a base64-encoded 32-byte key.');
        }

        $this->key = $key;
    }

    public function encrypt(string $plainText): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipherText = sodium_crypto_secretbox($plainText, $nonce, $this->key);

        return base64_encode($nonce.$cipherText);
    }

    public function decrypt(string $payload): string
    {
        $decoded = base64_decode($payload, true);
        if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Le secret stocké est invalide.');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipherText = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plainText = sodium_crypto_secretbox_open($cipherText, $nonce, $this->key);

        if ($plainText === false) {
            throw new \RuntimeException('Impossible de déchiffrer le secret.');
        }

        return $plainText;
    }
}
