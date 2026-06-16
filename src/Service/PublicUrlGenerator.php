<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PublicUrlGenerator
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(APP_PUBLIC_URL)%')]
        private string $publicUrl,
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {
    }

    /** @param array<string, mixed> $parameters */
    public function generate(string $route, array $parameters = []): string
    {
        $path = $this->urlGenerator->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_PATH);

        return rtrim($this->publicUrl, '/').'/'.ltrim($path, '/');
    }

    public function assertPubliclyReachable(): void
    {
        $scheme = strtolower((string) parse_url($this->publicUrl, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($this->publicUrl, PHP_URL_HOST));

        $isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

        if ($scheme !== 'https' || $host === '' || ($this->environment === 'prod' && $isLocalHost)) {
            throw new \DomainException(
                'Configurez APP_PUBLIC_URL avec l’adresse HTTPS publique de l’application avant d’envoyer cet e-mail.'
            );
        }
    }
}
