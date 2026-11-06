<?php

declare(strict_types=1);

namespace App\Administration\Controller;

use App\Account\Entity\SchoolClass;
use App\Account\Repository\SchoolClassRepository;
use App\Account\Repository\UserRepository;
use App\Account\Security\SchoolClassVoter;
use App\Account\Service\ClassCsvParser;
use App\Account\Service\StudentAccounts;
use App\Administration\Service\StudentDataManager;
use App\Progress\Domain\ProgressLedger;
use App\Progress\Entity\Validation;
use App\Progress\Repository\ValidationRepository;
use App\Referential\Service\ReferentialCatalog;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Classes et comptes (EP1, EP2, EP6, R4).
 */
#[IsGranted('ROLE_TEACHER')]
#[Route('/enseignant/classes')]
final class ClassController extends TeacherController
{
    #[Route('', name: 'teacher_classes', methods: ['GET'])]
    public function index(SchoolClassRepository $classes): Response
    {
        return $this->render('teacher/classes/index.html.twig', [
            'classes' => $classes->findForTeacher($this->teacher(), includeArchived: true),
        ]);
    }

    /** Import d'une liste collée (pas de dépôt de fichier, S15) et liste imprimable des mots de passe provisoires. */
    #[Route('/import', name: 'teacher_class_import', methods: ['GET', 'POST'])]
    public function import(Request $request, ClassCsvParser $parser, StudentAccounts $accounts, ReferentialCatalog $referential, ClockInterface $clock): Response
    {
        $levels = $referential->levels();
        $data = ['csv' => '', 'level' => ($levels[0] ?? null)?->getCode() ?? 'snt', 'year' => SchoolClass::currentSchoolYear($clock->now())];
        $errors = [];

        if ($request->isMethod('POST')) {
            $this->requireCsrf('class_import', $request->request->getString('_token'));
            $data = [
                'csv' => mb_substr($request->request->getString('csv'), 0, 20000),
                'level' => $request->request->getString('level'),
                'year' => $request->request->getString('year'),
            ];
            if (null === $referential->level($data['level'])) {
                $errors[] = 'Niveau inconnu.';
            }
            if (!preg_match('/^(\d{4})-(\d{4})$/', $data['year'], $m) || (int) $m[2] !== (int) $m[1] + 1) {
                $errors[] = 'Année scolaire au format 2026-2027.';
            }
            $parsed = $parser->parse($data['csv']);
            $errors = [...$errors, ...$parsed['errors']];
            if ([] === $errors && [] === $parsed['rows']) {
                $errors[] = 'Aucun élève dans la liste.';
            }
            if ([] === $errors) {
                $result = $accounts->import($this->teacher(), $parsed['rows'], $data['level'], $data['year']);
                if ([] === $result['errors']) {
                    // Affichage unique, sans redirection ni stockage : les mots de passe ne sont conservés nulle part.
                    $response = $this->render('teacher/classes/passwords.html.twig', [
                        'entries' => $result['created'],
                        'title' => 'Mots de passe provisoires',
                    ]);
                    $response->headers->set('Cache-Control', 'no-store');

                    return $response;
                }
                $errors = $result['errors'];
            }
        }

        return $this->render('teacher/classes/import.html.twig', [
            'data' => $data,
            'errors' => $errors,
            'levels' => $levels,
        ], new Response(status: [] === $errors ? 200 : 422));
    }

    #[Route('/{id}', name: 'teacher_class_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(SchoolClassVoter::MANAGE, subject: 'schoolClass')]
    public function show(SchoolClass $schoolClass, UserRepository $users, ReferentialCatalog $referential): Response
    {
        return $this->render('teacher/classes/show.html.twig', [
            'class' => $schoolClass,
            'students' => $users->findStudentsOf($schoolClass),
            'themes' => $referential->themesOf($schoolClass->getLevelCode()),
        ]);
    }

    /** EP6 : pour chaque élément, qui l'a validé et qui ne l'a pas validé. */
    #[Route('/{id}/synthese', name: 'teacher_class_overview', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(SchoolClassVoter::MANAGE, subject: 'schoolClass')]
    public function overview(SchoolClass $schoolClass, UserRepository $users, ValidationRepository $validations, ReferentialCatalog $referential): Response
    {
        $students = $users->findStudentsOf($schoolClass);
        $byStudent = $validations->findForStudents($students);
        $ledgers = [];
        foreach ($students as $student) {
            $ledgers[(int) $student->getId()] = new ProgressLedger(array_map(static fn (Validation $v) => $v->toEvent(), $byStudent[(int) $student->getId()] ?? []));
        }
        $rows = [];
        foreach ($referential->themesOf($schoolClass->getLevelCode()) as $theme) {
            foreach ($referential->itemsOfTheme($theme) as $item) {
                $done = [];
                $todo = [];
                foreach ($students as $student) {
                    if ($ledgers[(int) $student->getId()]->isValidated($item->getCode())) {
                        $done[] = $student;
                    } else {
                        $todo[] = $student;
                    }
                }
                $rows[] = ['theme' => $theme, 'item' => $item, 'done' => $done, 'todo' => $todo];
            }
        }

        return $this->render('teacher/classes/overview.html.twig', ['class' => $schoolClass, 'rows' => $rows, 'count' => \count($students)]);
    }

    #[Route('/{id}/archiver', name: 'teacher_class_archive', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(SchoolClassVoter::MANAGE, subject: 'schoolClass')]
    public function archive(SchoolClass $schoolClass, Request $request, StudentAccounts $accounts): Response
    {
        $this->requireCsrf('class_archive_'.$schoolClass->getId(), $request->request->getString('_token'));
        $accounts->archiveClass($this->teacher(), $schoolClass);
        $this->addFlash('success', \sprintf('Classe %s archivée : ses élèves ne peuvent plus se connecter.', $schoolClass->getName()));

        return $this->redirectToRoute('teacher_classes');
    }

    /** R4 : suppression de la classe et de toutes les données de ses élèves, en un geste (confirmation par saisie du nom). */
    #[Route('/{id}/supprimer', name: 'teacher_class_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(SchoolClassVoter::MANAGE, subject: 'schoolClass')]
    public function delete(SchoolClass $schoolClass, Request $request, StudentDataManager $data): Response
    {
        $this->requireCsrf('class_delete_'.$schoolClass->getId(), $request->request->getString('_token'));
        if (trim($request->request->getString('confirmation')) !== $schoolClass->getName()) {
            $this->addFlash('error', 'Pour supprimer, recopie exactement le nom de la classe.');

            return $this->redirectToRoute('teacher_class_show', ['id' => $schoolClass->getId()]);
        }
        $name = $schoolClass->getName();
        $count = $data->deleteClass($this->teacher(), $schoolClass);
        $this->addFlash('success', \sprintf('Classe %s supprimée avec les données de %d élève(s).', $name, $count));

        return $this->redirectToRoute('teacher_classes');
    }
}
