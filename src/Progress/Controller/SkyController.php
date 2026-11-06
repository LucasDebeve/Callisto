<?php

declare(strict_types=1);

namespace App\Progress\Controller;

use App\Account\Entity\User;
use App\Content\Service\ContentCatalog;
use App\Progress\Service\HistoryBuilder;
use App\Progress\Service\ProgressRecorder;
use App\Progress\Service\SkyBuilder;
use App\Referential\Service\ReferentialCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace élève : « Mon ciel », constellations et historique (EE2 à EE4). L'élève ne voit que ses données.
 */
#[IsGranted('ROLE_STUDENT')]
final class SkyController extends AbstractController
{
    public function __construct(
        private readonly ProgressRecorder $progress,
        private readonly SkyBuilder $sky,
        private readonly ReferentialCatalog $referential,
        private readonly HistoryBuilder $history,
        private readonly ContentCatalog $content,
    ) {
    }

    #[Route('/ciel', name: 'student_sky', methods: ['GET'])]
    public function sky(): Response
    {
        $student = $this->student();
        $level = $student->getLevelCode();
        $ledger = $this->progress->ledgerFor($student);

        return $this->render('progress/sky.html.twig', [
            'level' => null === $level ? null : $this->referential->level($level),
            'constellations' => null === $level ? [] : $this->sky->constellations($level, $ledger),
            'nextSteps' => null === $level ? [] : $this->sky->nextSteps($level, $ledger),
            'recent' => \array_slice($this->history->timeline($student), 0, 5),
        ]);
    }

    #[Route('/ciel/{code}', name: 'student_constellation', requirements: ['code' => '[a-z0-9._-]+'], methods: ['GET'])]
    public function constellation(string $code, Request $request): Response
    {
        $student = $this->student();
        $theme = $this->referential->theme($code);
        if (null === $theme || $theme->isArchived() || $theme->getLevel()->getCode() !== $student->getLevelCode()) {
            throw $this->createNotFoundException();
        }
        $ledger = $this->progress->ledgerFor($student);
        $session = $request->getSession();
        $lighting = $session instanceof FlashBagAwareSessionInterface ? $session->getFlashBag()->get('star_lit') : [];

        return $this->render('progress/constellation.html.twig', [
            'theme' => $theme,
            'map' => $this->sky->constellation($theme, $ledger),
            'selected' => $request->query->getString('etoile'),
            'lighting' => $lighting,
            'histories' => $this->history->perItem($student),
            'subject' => null,
            'coursesByItem' => $this->content->coursesByItem(),
        ]);
    }

    #[Route('/historique', name: 'student_history', methods: ['GET'])]
    public function history(): Response
    {
        return $this->render('progress/history.html.twig', [
            'timeline' => $this->history->timeline($this->student()),
        ]);
    }

    private function student(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
