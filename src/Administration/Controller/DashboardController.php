<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Account\Repository\SchoolClassRepository;
use App\Content\Repository\ContentImportRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TEACHER')]
final class DashboardController extends TeacherController
{
    #[Route('/enseignant', name: 'teacher_dashboard', methods: ['GET'])]
    public function index(SchoolClassRepository $classes, ContentImportRepository $imports): Response
    {
        return $this->render('teacher/dashboard.html.twig', [
            'classes' => $classes->findForTeacher($this->teacher()),
            'lastImport' => $imports->findOneBy([], ['importedAt' => 'DESC']),
        ]);
    }
}
