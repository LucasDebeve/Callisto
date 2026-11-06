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

#[IsGranted('ROLE_STUDENT')]
final class PreferencesController extends AbstractController
{
    #[Route('/preferences', name: 'student_preferences', methods: ['GET', 'POST'])]
    public function index(Request $request, StudentAccounts $accounts): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        $form = $this->createForm(ChangePasswordType::class, null, ['username' => $user->getUsername(), 'require_current' => true]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $accounts->changeOwnPassword($user, (string) $form->get('newPassword')->getData());
            $request->getSession()->migrate(true);
            $this->addFlash('success', 'Mot de passe modifié.');

            return $this->redirectToRoute('student_preferences');
        }

        return $this->render('account/preferences.html.twig', ['form' => $form, 'username' => $user->getUsername()], new Response(status: $form->isSubmitted() ? 422 : 200));
    }
}
