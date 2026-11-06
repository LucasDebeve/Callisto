<?php

declare(strict_types=1);

namespace App\Quiz\Controller;

use App\Account\Entity\User;
use App\Content\Service\ContentCatalog;
use App\Progress\Service\ProgressRecorder;
use App\Quiz\Domain\QuestionType;
use App\Quiz\Entity\Attempt;
use App\Quiz\Repository\AttemptRepository;
use App\Quiz\Security\AttemptVoter;
use App\Quiz\Service\QuizRunner;
use App\Referential\Domain\ItemType;
use App\Referential\Service\ReferentialCatalog;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * QCM : une question par vue, navigation, récapitulatif, envoi, correction (EE6, EQ1 à EQ7).
 * Les bonnes réponses ne sont jamais envoyées au navigateur avant la soumission (EQ5).
 */
#[IsGranted('ROLE_STUDENT')]
final class QuizController extends AbstractController
{
    public function __construct(
        private readonly QuizRunner $runner,
        private readonly ReferentialCatalog $referential,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/qcm/{code}', name: 'quiz_intro', requirements: ['code' => '[a-z0-9._-]+'], methods: ['GET'])]
    public function intro(string $code, AttemptRepository $attempts, ProgressRecorder $progress, ContentCatalog $content): Response
    {
        $item = $this->referential->item($code);
        if (null === $item || $item->isArchived() || ItemType::Knowledge !== $item->getType()) {
            throw $this->createNotFoundException();
        }
        $student = $this->student();

        return $this->render('quiz/intro.html.twig', [
            'item' => $item,
            'available' => $this->runner->canStart($item),
            'state' => $progress->ledgerFor($student)->stateOf($code),
            'open' => $attempts->findOpenFor($student, $code, $this->clock->now()),
            'attempts' => $attempts->findSubmittedFor($student, $code, 10),
            'courses' => $content->coursesForItem($code),
        ]);
    }

    #[Route('/qcm/{code}/commencer', name: 'quiz_start', requirements: ['code' => '[a-z0-9._-]+'], methods: ['POST'])]
    #[IsCsrfTokenValid('quiz_start', tokenKey: '_token')]
    public function start(string $code, Request $request, #[Autowire(service: 'limiter.quiz_start')] RateLimiterFactoryInterface $limiter): Response
    {
        $item = $this->referential->item($code);
        if (null === $item || !$this->runner->canStart($item)) {
            throw $this->createNotFoundException();
        }
        $student = $this->student();
        if (!$limiter->create('student-'.$student->getId())->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(60, 'Trop de tentatives commencées. Patiente une minute.');
        }
        $attempt = $this->runner->start($student, $item);

        return $this->redirectToRoute('quiz_question', ['id' => $attempt->getId(), 'n' => 1]);
    }

    #[Route('/tentative/{id}/question/{n}', name: 'quiz_question', requirements: ['n' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(AttemptVoter::OWN, subject: 'attempt', statusCode: 404)]
    public function question(#[MapEntity(id: 'id')] Attempt $attempt, int $n, Request $request): Response
    {
        if ($attempt->isSubmitted()) {
            return $this->redirectToRoute('quiz_result', ['id' => $attempt->getId()]);
        }
        if ($attempt->isExpired($this->clock->now())) {
            return $this->render('quiz/expired.html.twig', ['attempt' => $attempt, 'item' => $this->referential->item($attempt->getItemCode())]);
        }
        $index = $n - 1;
        if ($index < 0 || $index >= $attempt->getTotal()) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('quiz_answer_'.$attempt->getId(), $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $question = $attempt->getPublicQuestion($index);
            $answer = match ($question['type']) {
                QuestionType::Multiple => array_values(array_filter($request->request->all('reponse'), 'is_string')),
                default => $request->request->has('reponse') && \is_string($request->request->all()['reponse'] ?? null) ? $request->request->getString('reponse') : null,
            };
            $this->runner->answer($attempt, $index, $answer);

            $go = $request->request->getString('aller');
            if ('precedente' === $go && $index > 0) {
                return $this->redirectToRoute('quiz_question', ['id' => $attempt->getId(), 'n' => $n - 1]);
            }
            if ('suivante' === $go && $n < $attempt->getTotal()) {
                return $this->redirectToRoute('quiz_question', ['id' => $attempt->getId(), 'n' => $n + 1]);
            }
            if (preg_match('/^q(\d+)$/', $go, $m) && (int) $m[1] >= 1 && (int) $m[1] <= $attempt->getTotal()) {
                return $this->redirectToRoute('quiz_question', ['id' => $attempt->getId(), 'n' => (int) $m[1]]);
            }

            return $this->redirectToRoute('quiz_review', ['id' => $attempt->getId()]);
        }

        return $this->render('quiz/question.html.twig', [
            'attempt' => $attempt,
            'item' => $this->referential->item($attempt->getItemCode()),
            'question' => $attempt->getPublicQuestion($index),
            'answer' => $attempt->getAnswer($index),
            'n' => $n,
        ]);
    }

    #[Route('/tentative/{id}/recapitulatif', name: 'quiz_review', methods: ['GET'])]
    #[IsGranted(AttemptVoter::OWN, subject: 'attempt', statusCode: 404)]
    public function review(#[MapEntity(id: 'id')] Attempt $attempt): Response
    {
        if ($attempt->isSubmitted()) {
            return $this->redirectToRoute('quiz_result', ['id' => $attempt->getId()]);
        }
        if ($attempt->isExpired($this->clock->now())) {
            return $this->render('quiz/expired.html.twig', ['attempt' => $attempt, 'item' => $this->referential->item($attempt->getItemCode())]);
        }

        return $this->render('quiz/review.html.twig', [
            'attempt' => $attempt,
            'item' => $this->referential->item($attempt->getItemCode()),
        ]);
    }

    #[Route('/tentative/{id}/envoyer', name: 'quiz_submit', methods: ['POST'])]
    #[IsGranted(AttemptVoter::OWN, subject: 'attempt', statusCode: 404)]
    public function submit(#[MapEntity(id: 'id')] Attempt $attempt, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('quiz_submit_'.$attempt->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        if ($attempt->isSubmitted()) {
            $this->addFlash('info', 'Cette tentative a déjà été envoyée.');

            return $this->redirectToRoute('quiz_result', ['id' => $attempt->getId()]);
        }
        try {
            $newValidation = $this->runner->submit($attempt);
        } catch (\DomainException) {
            return $this->render('quiz/expired.html.twig', ['attempt' => $attempt, 'item' => $this->referential->item($attempt->getItemCode())], new Response(status: 409));
        }
        if ($newValidation) {
            $this->addFlash('star_lit', $attempt->getItemCode());
            $request->getSession()->set('quiz_new_validation_'.$attempt->getId(), true);
        }

        return $this->redirectToRoute('quiz_result', ['id' => $attempt->getId()]);
    }

    #[Route('/tentative/{id}/resultat', name: 'quiz_result', methods: ['GET'])]
    #[IsGranted(AttemptVoter::OWN, subject: 'attempt', statusCode: 404)]
    public function result(#[MapEntity(id: 'id')] Attempt $attempt, Request $request, ProgressRecorder $progress): Response
    {
        if (!$attempt->isSubmitted()) {
            return $this->redirectToRoute('quiz_review', ['id' => $attempt->getId()]);
        }
        $item = $this->referential->item($attempt->getItemCode());

        return $this->render('quiz/result.html.twig', [
            'attempt' => $attempt,
            'item' => $item,
            'questions' => $attempt->getCorrectedQuestions(),
            'newValidation' => (bool) $request->getSession()->remove('quiz_new_validation_'.$attempt->getId()),
            'validated' => $progress->ledgerFor($this->student())->isValidated($attempt->getItemCode()),
        ]);
    }

    private function student(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
