<?php

namespace App\Tests\Service;

use App\Service\PublicUrlGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PublicUrlGeneratorTest extends TestCase
{
    #[Test]
    public function itGeneratesLinksFromTheConfiguredPublicOrigin(): void
    {
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router
            ->expects(self::once())
            ->method('generate')
            ->with('app_login', [], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/connexion');

        $generator = new PublicUrlGenerator($router, 'https://gestion.example.com/', 'prod');

        self::assertSame('https://gestion.example.com/connexion', $generator->generate('app_login'));
        $generator->assertPubliclyReachable();
    }

    #[Test]
    public function itRejectsLocalhostForLinksSentByEmail(): void
    {
        $generator = new PublicUrlGenerator(
            $this->createStub(UrlGeneratorInterface::class),
            'https://127.0.0.1:8000',
            'prod',
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('APP_PUBLIC_URL');

        $generator->assertPubliclyReachable();
    }

    #[Test]
    public function itAllowsLocalHttpsDuringDevelopment(): void
    {
        $generator = new PublicUrlGenerator(
            $this->createStub(UrlGeneratorInterface::class),
            'https://127.0.0.1:8000',
            'dev',
        );

        $generator->assertPubliclyReachable();

        self::assertTrue(true);
    }
}
