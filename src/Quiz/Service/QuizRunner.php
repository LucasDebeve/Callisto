<?php

declare(strict_types=1);

namespace App\Quiz\Service;

use App\Account\Entity\User;
use App\Progress\Service\ProgressRecorder;
use App\Quiz\Domain\QuestionDrawer;
use App\Quiz\Entity\Attempt;
use App\Quiz\Entity\Question;
use App\Quiz\Repository\AttemptRepository;
use App\Quiz\Repository\QuestionRepository;
use App\Referential\Domain\ItemType;
use App\Referential\Entity\Item;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Déroulé d'un QCM, entièrement côté serveur (EQ2 à EQ6, S10).
 */
final readonly class QuizRunner
{
    public function __construct(
        private QuestionRepository $questions,
        private AttemptRepository $attempts,
        private QuestionDrawer $drawer,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        private ProgressRecorder $progress,
        private int $quizMaxDurationMinutes,
    ) {
    }

    public function canStart(Item $item): bool
    {
        return ItemType::Knowledge === $item->getType() && !$item->isArchived() && [] !== $this->questions->findActiveForItem($item->getCode());
    }

    /** Reprend une tentative ouverte, ou en crée une nouvelle par tirage. */
    public function start(User $student, Item $item): Attempt
    {
        if (!$this->canStart($item)) {
            throw new \DomainException('Aucun QCM disponible pour cet élément.');
        }
        $now = $this->clock->now();
        $open = $this->attempts->findOpenFor($student, $item->getCode(), $now);
        if (null !== $open) {
            return $open;
        }

        $drawn = $this->drawer->draw($this->questions->findActiveForItem($item->getCode()), $item->getQuestionsPerAttempt());
        $snapshot = array_map(fn (Question $q) => [
            'code' => $q->getCode(),
            'type' => $q->getType()->value,
            'promptHtml' => $q->getPromptHtml(),
            'codeHtml' => $q->getCodeHtml(),
            'choices' => $this->drawer->shuffle($q->getChoices()),
            'acceptedAnswers' => $q->getAcceptedAnswers(),
            'explanationHtml' => $q->getExplanationHtml(),
        ], $drawn);

        $attempt = new Attempt($student, $item->getCode(), $item->getThreshold(), $snapshot, $now, $now->modify(\sprintf('+%d minutes', $this->quizMaxDurationMinutes)));
        $this->em->persist($attempt);
        $this->em->flush();

        return $attempt;
    }

    /**
     * @param string|list<string>|null $answer
     */
    public function answer(Attempt $attempt, int $index, string|array|null $answer): void
    {
        $attempt->recordAnswer($index, $answer, $this->clock->now());
        $this->em->flush();
    }

    /** Corrige la tentative et, si le seuil est atteint, crée la validation du savoir. Renvoie vrai si nouvelle validation. */
    public function submit(Attempt $attempt): bool
    {
        $passed = $attempt->submit($this->clock->now());
        $this->em->flush();

        return $passed && $this->progress->grantFromQuiz($attempt->getStudent(), $attempt->getItemCode(), $attempt->getId());
    }
}
