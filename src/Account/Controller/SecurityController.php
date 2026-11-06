<?php

declare(strict_types=1);

namespace App\Account\Controller;

use App\Account\Entity\User;
use App\Account\Form\ChangePasswordType;
use App\Account\Service\StudentAccounts;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $auth, Request $request): Response
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('student_sky');
        }

        return $this->render('account/login.html.twig', [
            'last_username' => $auth->getLastUsername(),
            'error' => $auth->getLastAuthenticationError(),
            'expired' => $request->query->has('expiree'),
        ]);
    }

    #[Route('/deconnexion', name: 'app_logout', methods: ['GET', 'POST'])]
    public function logout(): never
    {
        throw new \LogicException('Intercepté par le pare-feu.');
    }

    /** EE1 : changement obligatoire du mot de passe provisoire. */
    #[Route('/premiere-connexion', name: 'app_first_password', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_STUDENT')]
    public function firstPassword(Request $request, StudentAccounts $accounts): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        if (!$user->mustChangePassword()) {
            return $this->redirectToRoute('student_sky');
        }
        $form = $this->createForm(ChangePasswordType::class, null, ['username' => $user->getUsername()]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $accounts->changeOwnPassword($user, (string) $form->get('newPassword')->getData());
            $request->getSession()->migrate(true);
            $this->addFlash('success', 'Mot de passe enregistré. Bienvenue dans ton ciel.');

            return $this->redirectToRoute('student_sky');
        }

        return $this->render('account/first_password.html.twig', ['form' => $form, 'username' => $user->getUsername()], new Response(status: $form->isSubmitted() ? 422 : 200));
    }
}
