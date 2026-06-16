<?php

namespace App\Twig;

use App\Entity\User;
use App\Service\ModuleAccessService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function __construct(private readonly ModuleAccessService $moduleAccessService)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('accessible_modules', $this->accessibleModules(...)),
        ];
    }

    public function accessibleModules(?User $user): array
    {
        return $user ? $this->moduleAccessService->getAccessibleModules($user) : [];
    }
}
