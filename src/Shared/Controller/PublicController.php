<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Account\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->getUser() instanceof User ? $this->redirectToRoute('student_sky') : $this->redirectToRoute('app_login');
    }

    /** R6 : mentions légales et confidentialité en langage clair. */
    #[Route('/mentions-legales', name: 'legal_notice', methods: ['GET'])]
    public function legal(): Response
    {
        return $this->render('public/legal.html.twig');
    }

    /** M13 : point de contrôle de santé (supervision externe). Aucune information sensible. */
    #[Route('/sante', name: 'health', methods: ['GET'])]
    public function health(Connection $connection): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1');
            $database = 'ok';
        } catch (\Throwable) {
            $database = 'erreur';
        }
        $response = new JsonResponse(['statut' => 'ok' === $database ? 'ok' : 'degrade', 'base' => $database], 'ok' === $database ? 200 : 503);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
