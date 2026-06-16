<?php

namespace App\Tests\Service;

use App\Service\PasswordCipher;
use PHPUnit\Framework\TestCase;

final class PasswordCipherTest extends TestCase
{
    public function testEncryptsAndDecryptsWithoutExposingPlainText(): void
    {
        $cipher = new PasswordCipher(base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
        $plainText = 'Secret complexe 123!';

        $encrypted = $cipher->encrypt($plainText);

        self::assertNotSame($plainText, $encrypted);
        self::assertSame($plainText, $cipher->decrypt($encrypted));
    }

    public function testRejectsInvalidKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PasswordCipher('invalid');
    }

    public function testRejectsTamperedPayload(): void
    {
        $cipher = new PasswordCipher(base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
        $payload = base64_decode($cipher->encrypt('secret'), true);
        \assert(is_string($payload));
        $payload[30] = chr(ord($payload[30]) ^ 1);

        $this->expectException(\RuntimeException::class);
        $cipher->decrypt(base64_encode($payload));
    }
}
