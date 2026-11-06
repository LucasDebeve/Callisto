<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Content\Import\ContentImporter;
use App\Content\Repository\ContentImportRepository;
use App\Quiz\Repository\QuestionRepository;
use App\Referential\Domain\ItemType;
use App\Referential\Service\ReferentialCatalog;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Import des contenus depuis le dépôt (EC2) et consultation de la banque de QCM.
 * Le contenu est lu sur le serveur, jamais déposé depuis le navigateur (S15).
 */
#[IsGranted('ROLE_TEACHER')]
final class ContentAdminController extends TeacherController
{
    #[Route('/enseignant/contenus', name: 'teacher_content', methods: ['GET'])]
    public function report(ContentImporter $importer, ContentImportRepository $imports): Response
    {
        [$report] = $importer->analyze();

        return $this->render('teacher/content.html.twig', [
            'report' => $report,
            'imports' => $imports->findBy([], ['importedAt' => 'DESC'], 10),
        ]);
    }

    #[Route('/enseignant/contenus/publier', name: 'teacher_content_publish', methods: ['POST'])]
    public function publish(Request $request, ContentImporter $importer): Response
    {
        $this->requireCsrf('content_publish', $request->request->getString('_token'));
        [$report, $content] = $importer->analyze();
        if (!$report->isValid()) {
            $this->addFlash('error', 'Le contenu contient des erreurs : rien n\'a été publié.');

            return $this->redirectToRoute('teacher_content');
        }
        $importer->publish($content, $report, $this->teacher()->getUserIdentifier());
        $this->addFlash('success', 'Contenus publiés.');

        return $this->redirectToRoute('teacher_content');
    }

    #[Route('/enseignant/qcm', name: 'teacher_quiz_bank', methods: ['GET'])]
    public function bank(Request $request, ReferentialCatalog $referential, QuestionRepository $questions): Response
    {
        $counts = $questions->countActiveByItem();
        $selected = $referential->item($request->query->getString('savoir'));

        $levels = $referential->levels();
        $items = [];
        foreach ($levels as $level) {
            $items[$level->getCode()] = $referential->itemsOfLevel($level->getCode(), ItemType::Knowledge);
        }

        return $this->render('teacher/quiz_bank.html.twig', [
            'levels' => $levels,
            'itemsByLevel' => $items,
            'counts' => $counts,
            'selected' => $selected,
            'questions' => null === $selected ? [] : $questions->findActiveForItem($selected->getCode()),
        ]);
    }
}
