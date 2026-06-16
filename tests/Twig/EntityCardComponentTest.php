<?php

namespace App\Tests\Twig;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class EntityCardComponentTest extends KernelTestCase
{
    #[Test]
    public function itRendersSharedMetadataAndActions(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get(Environment::class);

        $template = $twig->createTemplate(<<<'TWIG'
{% embed 'components/_entity_card.html.twig' with {
    title: 'Carte test',
    title_remote_url: '/carte-test',
    subtitle: 'Sous-titre',
    icon: 'bi-check2',
    status_label: 'Actif',
    created_at: created_at,
    updated_at: updated_at
} %}
    {% block top_actions %}
        <button class="btn btn-outline-secondary" title="Modifier">Modifier</button>
    {% endblock %}
    {% block body %}
        <p>Contenu de test</p>
    {% endblock %}
    {% block actions %}
        <button class="btn btn-primary">Action</button>
    {% endblock %}
{% endembed %}
TWIG);

        $html = $template->render([
            'created_at' => new \DateTimeImmutable('2026-06-14 09:30:00'),
            'updated_at' => new \DateTimeImmutable('2026-06-15 10:45:00'),
        ]);

        self::assertStringContainsString('Carte test', $html);
        self::assertStringContainsString('href="/carte-test"', $html);
        self::assertStringContainsString('app-card-title-link', $html);
        self::assertStringContainsString('app-card-accent-primary', $html);
        self::assertStringContainsString('app-card-heading', $html);
        self::assertStringContainsString('app-card-top-actions', $html);
        self::assertStringContainsString('app-card-subtitle', $html);
        self::assertStringNotContainsString('app-card-content', $html);
        self::assertStringNotContainsString('Contenu de test', $html);
        self::assertStringContainsString('app-card-status', $html);
        self::assertStringContainsString('Créé le 14/06/2026 à 09:30', $html);
        self::assertStringContainsString('Modifié le 15/06/2026 à 10:45', $html);
        self::assertStringContainsString('card-footer', $html);
        self::assertStringContainsString('Action', $html);
    }

    #[Test]
    public function itCanRenderDetailedBodyWhenCompactModeIsDisabled(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get(Environment::class);

        $template = $twig->createTemplate(<<<'TWIG'
{% embed 'components/_entity_card.html.twig' with {
    title: 'Carte detaillee',
    compact: false,
    created_at: created_at
} %}
    {% block body %}
        <p>Contenu de test</p>
    {% endblock %}
{% endembed %}
TWIG);

        $html = $template->render([
            'created_at' => new \DateTimeImmutable('2026-06-14 09:30:00'),
        ]);

        self::assertStringContainsString('app-card-content', $html);
        self::assertStringContainsString('Contenu de test', $html);
    }
}
