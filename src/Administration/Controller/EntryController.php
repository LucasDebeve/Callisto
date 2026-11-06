<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Account\Entity\SchoolClass;
use App\Account\Repository\UserRepository;
use App\Account\Security\SchoolClassVoter;
use App\Account\Security\StudentVoter;
use App\Progress\Domain\ProgressLedger;
use App\Progress\Entity\Validation;
use App\Progress\Repository\ValidationRepository;
use App\Progress\Service\ProgressRecorder;
use App\Referential\Domain\ItemType;
use App\Referential\Entity\Item;
use App\Referential\Service\ReferentialCatalog;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tableau de saisie : élèves en lignes, savoir-faire et compétences d'un chapitre en colonnes (EP3, EP4).
 * Seul point d'écriture manuelle des validations, dans le périmètre enseignant (S8).
 */
#[IsGranted('ROLE_TEACHER')]
final class EntryController extends TeacherController
{
    #[Route('/enseignant/classes/{id}/saisie', name: 'teacher_entry', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(SchoolClassVoter::MANAGE, subject: 'schoolClass')]
    public function grid(SchoolClass $schoolClass, Request $request, ReferentialCatalog $referential, UserRepository $users, ValidationRepository $validations, ClockInterface $clock): Response
    {
        $chapters = [];
        foreach ($referential->themesOf($schoolClass->getLevelCode()) as $theme) {
            foreach ($theme->getChapters() as $chapter) {
                $chapters[] = $chapter;
            }
        }
        $selected = $referential->chapter($request->query->getString('chapitre')) ?? ($chapters[0] ?? null);
        $items = null === $selected ? [] : array_values(array_filter(
            $referential->itemsOfChapter($selected),
            static fn (Item $i) => ItemType::Knowledge !== $i->getType() || $request->query->getBoolean('savoirs'),
        ));
        $students = $users->findStudentsOf($schoolClass);
        $byStudent = $validations->findForStudents($students);
        $states = [];
        foreach ($students as $student) {
            $ledger = new ProgressLedger(array_map(static fn (Validation $v) => $v->toEvent(), $byStudent[(int) $student->getId()] ?? []));
            foreach ($items as $item) {
                $states[(int) $student->getId()][$item->getCode()] = $ledger->stateOf($item->getCode());
            }
        }

        return $this->render('teacher/entry.html.twig', [
            'class' => $schoolClass,
            'chapters' => $chapters,
            'chapter' => $selected,
            'items' => $items,
            'students' => $students,
            'states' => $states,
            'today' => $clock->now()->format('Y-m-d'),
            'withKnowledge' => $request->query->getBoolean('savoirs'),
        ]);
    }

    #[Route('/enseignant/saisie', name: 'teacher_entry_save', methods: ['POST'])]
    public function save(Request $request, UserRepository $users, ProgressRecorder $progress, ReferentialCatalog $referential, ClockInterface $clock): Response
    {
        $this->requireCsrf('entry', $request->request->getString('_token'));
        $student = $users->findStudent($request->request->getInt('student'));
        if (null === $student || !$this->isGranted(StudentVoter::MANAGE, $student)) {
            throw $this->createNotFoundException();
        }
        $itemCode = $request->request->getString('item');
        $item = $referential->item($itemCode);
        if (null === $item || $item->isArchived()) {
            throw $this->createNotFoundException();
        }

        $today = $clock->now()->setTime(0, 0);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->request->getString('date')) ?: $today;
        if ($date > $today || $date < $today->modify('-2 years')) {
            $date = $today;
        }
        $comment = $request->request->getString('comment');

        if ('retirer' === $request->request->getString('action')) {
            $progress->revoke($this->teacher(), $student, $itemCode, $comment);
        } else {
            $progress->grantByTeacher($this->teacher(), $student, $itemCode, $date, $comment);
        }
        $state = $progress->ledgerFor($student)->stateOf($itemCode);

        if ($request->headers->has('X-Requested-With') || 'json' === $request->getPreferredFormat()) {
            return new JsonResponse([
                'validated' => $state->validated,
                'date' => $state->validatedOn?->format('d/m/Y'),
                'comment' => $state->comment,
            ]);
        }

        $this->addFlash('success', $state->validated ? 'Validation enregistrée.' : 'Validation retirée.');

        return $this->redirectToRoute('teacher_entry', [
            'id' => $student->getSchoolClass()?->getId(),
            'chapitre' => $item->getChapter()->getCode(),
        ]);
    }
}
