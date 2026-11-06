<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Account\Entity\User;
use App\Account\Security\StudentVoter;
use App\Account\Service\StudentAccounts;
use App\Administration\Service\StudentDataManager;
use App\Progress\Domain\GradeCalculator;
use App\Progress\Service\HistoryBuilder;
use App\Progress\Service\ProgressRecorder;
use App\Progress\Service\SkyBuilder;
use App\Referential\Service\ReferentialCatalog;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Vue d'un élève (EP5), réinitialisation (EP2), export et suppression (R5).
 */
#[IsGranted('ROLE_TEACHER')]
#[Route('/enseignant/eleves/{id}', requirements: ['id' => '\d+'])]
final class StudentController extends TeacherController
{
    #[Route('', name: 'teacher_student_show', methods: ['GET'])]
    #[IsGranted(StudentVoter::MANAGE, subject: 'student', statusCode: 404)]
    public function show(User $student, ProgressRecorder $progress, SkyBuilder $sky, HistoryBuilder $history, ReferentialCatalog $referential, GradeCalculator $grades): Response
    {
        $ledger = $progress->ledgerFor($student);
        $level = (string) $student->getLevelCode();
        $items = $referential->itemsOfLevel($level);

        return $this->render('teacher/student/show.html.twig', [
            'student' => $student,
            'constellations' => $sky->constellations($level, $ledger),
            'timeline' => $history->timeline($student),
            'grade' => $grades->compute($sky->countByType($items, $ledger)),
            'counts' => $sky->countByType($items, $ledger),
        ]);
    }

    #[Route('/constellation/{code}', name: 'teacher_student_constellation', requirements: ['code' => '[a-z0-9._-]+'], methods: ['GET'])]
    #[IsGranted(StudentVoter::MANAGE, subject: 'student', statusCode: 404)]
    public function constellation(User $student, string $code, Request $request, ProgressRecorder $progress, SkyBuilder $sky, HistoryBuilder $history, ReferentialCatalog $referential): Response
    {
        $theme = $referential->theme($code);
        if (null === $theme || $theme->getLevel()->getCode() !== $student->getLevelCode()) {
            throw $this->createNotFoundException();
        }

        return $this->render('teacher/student/constellation.html.twig', [
            'theme' => $theme,
            'map' => $sky->constellation($theme, $progress->ledgerFor($student)),
            'selected' => $request->query->getString('etoile'),
            'lighting' => [],
            'histories' => $history->perItem($student),
            'subject' => $student,
        ]);
    }

    /** Retrait d'une validation saisie par erreur, y compris d'un savoir validé par QCM. */
    #[Route('/retrait', name: 'teacher_student_revoke', methods: ['POST'])]
    #[IsGranted(StudentVoter::MANAGE, subject: 'student', statusCode: 404)]
    public function revoke(User $student, Request $request, ProgressRecorder $progress): Response
    {
        $this->requireCsrf('revoke_'.$student->getId(), $request->request->getString('_token'));
        try {
            $done = null !== $progress->revoke($this->teacher(), $student, $request->request->getString('item'), $request->request->getString('comment'));
            $this->addFlash($done ? 'success' : 'info', $done ? 'Validation retirée. Le retrait est enregistré dans l\'historique.' : 'Cet élément n\'était pas validé.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('teacher_student_show', ['id' => $student->getId()]);
    }

    #[Route('/mot-de-passe', name: 'teacher_student_reset', methods: ['POST'])]
    #[IsGranted(StudentVoter::MANAGE, subject: 'student', statusCode: 404)]
    public function resetPassword(User $student, Request $request, StudentAccounts $accounts): Response
    {
        $this->requireCsrf('reset_'.$student->getId(), $request->request->getString('_token'));
        $password = $accounts->resetPassword($this->teacher(), $student);
        $response = $this->render('teacher/classes/passwords.html.twig', [
            'entries' => [['user' => $student, 'password' => $password]],
            'title' => 'Nouveau mot de passe provisoire',
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/export', name: 'teacher_student_export', methods: ['GET'])]
    #[IsGranted(StudentVoter::MANAGE, subject: 'student', statusCode: 404)]
    public function export(User $student, StudentDataManager $data): Response
    {
        $response = new JsonResponse($data->export($this->teacher(), $student), 200, [], false);
        $response->setEncodingOptions(\JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'callisto-'.$student->getUsername().'.json'));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/supprimer', name: 'teacher_student_delete', methods: ['POST'])]
    #[IsGranted(StudentVoter::MANAGE, subject: 'student', statusCode: 404)]
    public function delete(User $student, Request $request, StudentDataManager $data): Response
    {
        $this->requireCsrf('delete_'.$student->getId(), $request->request->getString('_token'));
        $classId = $student->getSchoolClass()?->getId();
        if (trim($request->request->getString('confirmation')) !== $student->getUsername()) {
            $this->addFlash('error', 'Pour supprimer, recopie exactement l\'identifiant de l\'élève.');

            return $this->redirectToRoute('teacher_student_show', ['id' => $student->getId()]);
        }
        $data->deleteStudent($this->teacher(), $student);
        $this->addFlash('success', 'Compte et données de l\'élève supprimés.');

        return null === $classId ? $this->redirectToRoute('teacher_classes') : $this->redirectToRoute('teacher_class_show', ['id' => $classId]);
    }
}
