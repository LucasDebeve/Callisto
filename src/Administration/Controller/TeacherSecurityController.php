<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Account\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Connexion à l'espace enseignant : pare-feu propre, double authentification obligatoire (S5, S8).
 */
final class TeacherSecurityController extends AbstractController
{
    #[Route('/enseignant/connexion', name: 'teacher_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $auth, Request $request): Response
    {
        if ($this->getUser() instanceof User && $this->isGranted('ROLE_TEACHER')) {
            return $this->redirectToRoute('teacher_dashboard');
        }

        return $this->render('teacher/login.html.twig', [
            'last_username' => $auth->getLastUsername(),
            'error' => $auth->getLastAuthenticationError(),
            'expired' => $request->query->has('expiree'),
        ]);
    }

    #[Route('/enseignant/deconnexion', name: 'teacher_logout', methods: ['GET', 'POST'])]
    public function logout(): never
    {
        throw new \LogicException('Intercepté par le pare-feu.');
    }
}
