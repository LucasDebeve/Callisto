<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Administration\Repository\AuditEntryRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** EP7 : journal d'audit consultable. */
#[IsGranted('ROLE_TEACHER')]
final class AuditController extends TeacherController
{
    #[Route('/enseignant/journal', name: 'teacher_audit', methods: ['GET'])]
    public function index(Request $request, AuditEntryRepository $entries): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $action = $request->query->getString('action');
        $actor = $request->query->getString('qui');
        $list = $entries->search($action, $actor, $page, 51);

        return $this->render('teacher/audit.html.twig', [
            'entries' => \array_slice($list, 0, 50),
            'hasNext' => \count($list) > 50,
            'page' => $page,
            'action' => $action,
            'actor' => $actor,
            'actions' => $entries->distinctActions(),
        ]);
    }
}
