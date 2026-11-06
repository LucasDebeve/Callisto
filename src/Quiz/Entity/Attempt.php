<?php

declare(strict_types=1);

namespace App\Quiz\Entity;

use App\Account\Entity\User;
use App\Quiz\Domain\AnswerChecker;
use App\Quiz\Domain\QuestionType;
use App\Quiz\Domain\ThresholdPolicy;
use App\Quiz\Repository\AttemptRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Tentative de QCM, créée et corrigée par le serveur (S10). Elle conserve un instantané complet
 * des questions tirées, des réponses données, du score et des dates (EQ6).
 *
 * @phpstan-type Snapshot array{code: string, type: string, promptHtml: string, codeHtml: ?string, choices: list<array{key: string, html: string, correct: bool}>, acceptedAnswers: list<string>, explanationHtml: ?string}
 */
#[ORM\Entity(repositoryClass: AttemptRepository::class)]
#[ORM\Table(name: 'quiz_attempt')]
#[ORM\Index(columns: ['student_id', 'item_code'])]
class Attempt
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $student;

    #[ORM\Column(length: 120)]
    private string $itemCode;

    #[ORM\Column]
    private int $thresholdPercent;

    /** @var list<Snapshot> */
    #[ORM\Column(type: Types::JSON)]
    private array $questions;

    /** @var array<int, string|list<string>> index de la question => réponse */
    #[ORM\Column(type: Types::JSON)]
    private array $answers = [];

    /** @var list<bool> */
    #[ORM\Column(type: Types::JSON)]
    private array $results = [];

    #[ORM\Column(nullable: true)]
    private ?int $score = null;

    #[ORM\Column]
    private int $total;

    #[ORM\Column(nullable: true)]
    private ?bool $passed = null;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;

    /**
     * @param list<Snapshot> $questions
     */
    public function __construct(User $student, string $itemCode, int $thresholdPercent, array $questions, \DateTimeImmutable $startedAt, \DateTimeImmutable $expiresAt)
    {
        $this->id = Uuid::v7();
        $this->student = $student;
        $this->itemCode = $itemCode;
        $this->thresholdPercent = $thresholdPercent;
        $this->questions = $questions;
        $this->total = \count($questions);
        $this->startedAt = $startedAt;
        $this->expiresAt = $expiresAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getStudent(): User
    {
        return $this->student;
    }

    public function isOwnedBy(User $user): bool
    {
        return null !== $user->getId() && $this->student->getId() === $user->getId();
    }

    public function getItemCode(): string
    {
        return $this->itemCode;
    }

    public function getThresholdPercent(): int
    {
        return $this->thresholdPercent;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function isPassed(): bool
    {
        return true === $this->passed;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function isSubmitted(): bool
    {
        return null !== $this->submittedAt;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return !$this->isSubmitted() && $now > $this->expiresAt;
    }

    public function isOpen(\DateTimeImmutable $now): bool
    {
        return !$this->isSubmitted() && !$this->isExpired($now);
    }

    public function requiredScore(): int
    {
        return ThresholdPolicy::requiredScore($this->total, $this->thresholdPercent);
    }

    /**
     * Instantané complet, avec les bonnes réponses : à n'utiliser qu'après soumission.
     *
     * @return list<Snapshot>
     */
    public function getCorrectedQuestions(): array
    {
        if (!$this->isSubmitted()) {
            throw new \LogicException('La correction n\'est disponible qu\'après la soumission.');
        }

        return $this->questions;
    }

    /**
     * Vue d'une question sans aucune information de correction (EQ5).
     *
     * @return array{index: int, type: QuestionType, promptHtml: string, codeHtml: ?string, choices: list<array{key: string, html: string}>}
     */
    public function getPublicQuestion(int $index): array
    {
        $q = $this->questions[$index] ?? throw new \OutOfRangeException('Question inconnue.');

        return [
            'index' => $index,
            'type' => QuestionType::from($q['type']),
            'promptHtml' => $q['promptHtml'],
            'codeHtml' => $q['codeHtml'],
            'choices' => array_map(static fn (array $c) => ['key' => $c['key'], 'html' => $c['html']], $q['choices']),
        ];
    }

    /** @return array<int, string|list<string>> */
    public function getAnswers(): array
    {
        return $this->answers;
    }

    /** @return string|list<string>|null */
    public function getAnswer(int $index): string|array|null
    {
        return $this->answers[$index] ?? null;
    }

    public function isAnswered(int $index): bool
    {
        $answer = $this->answers[$index] ?? null;

        return null !== $answer && '' !== $answer && [] !== $answer;
    }

    public function countAnswered(): int
    {
        return \count(array_filter(array_keys($this->questions), fn (int $i) => $this->isAnswered($i)));
    }

    /** @return list<bool> */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Enregistre une réponse provisoire. Les clés de choix inconnues sont ignorées.
     *
     * @param string|list<string>|null $answer
     */
    public function recordAnswer(int $index, string|array|null $answer, \DateTimeImmutable $now): void
    {
        if (!$this->isOpen($now)) {
            throw new \DomainException('Cette tentative est close.');
        }
        $q = $this->questions[$index] ?? throw new \OutOfRangeException('Question inconnue.');
        $keys = array_column($q['choices'], 'key');
        $type = QuestionType::from($q['type']);

        $clean = match ($type) {
            QuestionType::Single => \is_string($answer) && \in_array($answer, $keys, true) ? $answer : null,
            QuestionType::Multiple => array_values(array_intersect($keys, \is_array($answer) ? $answer : [])),
            QuestionType::Short => \is_string($answer) ? mb_substr(trim($answer), 0, 200) : null,
        };

        if (null === $clean) {
            unset($this->answers[$index]);
        } else {
            $this->answers[$index] = $clean;
        }
    }

    /**
     * Corrige la tentative, une seule fois (S10). Renvoie vrai si le seuil est atteint.
     */
    public function submit(\DateTimeImmutable $now): bool
    {
        if ($this->isSubmitted()) {
            throw new \DomainException('Cette tentative a déjà été soumise.');
        }
        if ($this->isExpired($now)) {
            throw new \DomainException('Le délai de cette tentative est dépassé.');
        }

        $results = [];
        foreach ($this->questions as $index => $q) {
            $correctKeys = array_values(array_map(static fn (array $c) => $c['key'], array_filter($q['choices'], static fn (array $c) => $c['correct'])));
            $results[] = AnswerChecker::isCorrect(QuestionType::from($q['type']), $correctKeys, $q['acceptedAnswers'], $this->answers[$index] ?? null);
        }

        $this->results = $results;
        $this->score = \count(array_filter($results));
        $this->passed = ThresholdPolicy::isReached($this->score, $this->total, $this->thresholdPercent);
        $this->submittedAt = $now;

        return $this->passed;
    }
}
