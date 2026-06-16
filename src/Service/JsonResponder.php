<?php

namespace App\Service;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

final class JsonResponder
{
    /** @param array<string, mixed> $data */
    public function success(string $message, array $data = [], int $status = 200): JsonResponse
    {
        return new JsonResponse(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    /** @param array<string, mixed> $data */
    public function error(string $message, array $data = [], int $status = 400): JsonResponse
    {
        return new JsonResponse(['success' => false, 'message' => $message, 'data' => $data], $status);
    }

    public function invalidForm(FormInterface $form): JsonResponse
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        return $this->error($errors[0] ?? 'Le formulaire contient des erreurs.', ['errors' => $errors], 422);
    }
}
