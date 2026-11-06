<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Account\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Base des contrôleurs de l'espace enseignant.
 */
abstract class TeacherController extends AbstractController
{
    protected function teacher(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->isTeacher()) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    protected function requireCsrf(string $id, ?string $token): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
