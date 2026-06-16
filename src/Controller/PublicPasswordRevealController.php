<?php

namespace App\Controller;

use App\Service\UserAccessDeliveryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicPasswordRevealController extends AbstractController
{
    #[Route('/acces-secret/{token}', name: 'app_public_password_reveal', requirements: ['token' => '[A-Za-z0-9_-]{40,120}'], methods: ['GET'])]
    public function reveal(string $token, UserAccessDeliveryService $deliveryService): Response
    {
        $password = $deliveryService->consumePassword($token);
        $response = $this->render('security/public_password_reveal.html.twig', [
            'password' => $password,
        ]);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
