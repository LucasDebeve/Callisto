<?php

declare(strict_types=1);

namespace App\Quiz\Entity;

use App\Quiz\Domain\QuestionType;
use App\Quiz\Repository\QuestionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Question de la banque d'un savoir (EQ1). Les bonnes réponses restent côté serveur (EQ5).
 */
#[ORM\Entity(repositoryClass: QuestionRepository::class)]
#[ORM\Table(name: 'quiz_question')]
#[ORM\Index(columns: ['item_code', 'archived'])]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120, unique: true)]
    private string $code;

    /** Savoir auquel la question est rattachée. */
    #[ORM\Column(length: 120)]
    private string $itemCode;

    #[ORM\Column(length: 16, enumType: QuestionType::class)]
    private QuestionType $type;

    #[ORM\Column(type: Types::TEXT)]
    private string $promptHtml = '';

    /** Extrait de code déjà coloré (HTML nettoyé), facultatif. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $codeHtml = null;

    /** @var list<array{key: string, html: string, correct: bool}> */
    #[ORM\Column(type: Types::JSON)]
    private array $choices = [];

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $acceptedAnswers = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $explanationHtml = null;

    #[ORM\Column]
    private bool $archived = false;

    public function __construct(string $code, string $itemCode, QuestionType $type)
    {
        $this->code = $code;
        $this->itemCode = $itemCode;
        $this->type = $type;
    }

    /**
     * @param list<array{key: string, html: string, correct: bool}> $choices
     * @param list<string>                                          $acceptedAnswers
     */
    public function update(string $itemCode, QuestionType $type, string $promptHtml, ?string $codeHtml, array $choices, array $acceptedAnswers, ?string $explanationHtml): void
    {
        $this->itemCode = $itemCode;
        $this->type = $type;
        $this->promptHtml = $promptHtml;
        $this->codeHtml = $codeHtml;
        $this->choices = $choices;
        $this->acceptedAnswers = $acceptedAnswers;
        $this->explanationHtml = $explanationHtml;
        $this->archived = false;
    }

    public function archive(): void
    {
        $this->archived = true;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getItemCode(): string
    {
        return $this->itemCode;
    }

    public function getType(): QuestionType
    {
        return $this->type;
    }

    public function getPromptHtml(): string
    {
        return $this->promptHtml;
    }

    public function getCodeHtml(): ?string
    {
        return $this->codeHtml;
    }

    /** @return list<array{key: string, html: string, correct: bool}> */
    public function getChoices(): array
    {
        return $this->choices;
    }

    /** @return list<string> */
    public function getAcceptedAnswers(): array
    {
        return $this->acceptedAnswers;
    }

    public function getExplanationHtml(): ?string
    {
        return $this->explanationHtml;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }
}
