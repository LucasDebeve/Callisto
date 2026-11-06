<?php

declare(strict_types=1);

namespace App\Progress\Entity;

use App\Account\Entity\User;
use App\Progress\Domain\ValidationEvent;
use App\Progress\Domain\ValidationSource;
use App\Progress\Repository\ValidationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Enregistrement daté de l'historique de progression, en ajout seul (S11) : une validation ou un retrait.
 * Aucune méthode ne permet de le modifier ; un déclencheur PostgreSQL interdit UPDATE et DELETE.
 */
#[ORM\Entity(repositoryClass: ValidationRepository::class, readOnly: true)]
#[ORM\Table(name: 'progress_validation')]
#[ORM\Index(columns: ['student_id', 'item_code'])]
class Validation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $recordedAt;

    private function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $student,
        #[ORM\Column(length: 120)]
        private string $itemCode,
        #[ORM\Column]
        private bool $granted,
        #[ORM\Column(length: 16, enumType: ValidationSource::class)]
        private ValidationSource $source,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $validatedOn,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $comment,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: true)]
        private ?User $author,
        #[ORM\Column(type: UuidType::NAME, nullable: true)]
        private ?Uuid $attemptId,
        \DateTimeImmutable $recordedAt,
    ) {
        $this->recordedAt = $recordedAt;
    }

    public static function fromQuiz(User $student, string $itemCode, Uuid $attemptId, \DateTimeImmutable $now): self
    {
        return new self($student, $itemCode, true, ValidationSource::Quiz, $now, null, null, $attemptId, $now);
    }

    public static function byTeacher(User $student, string $itemCode, User $teacher, \DateTimeImmutable $validatedOn, ?string $comment, \DateTimeImmutable $now): self
    {
        return new self($student, $itemCode, true, ValidationSource::Teacher, $validatedOn, self::cleanComment($comment), $teacher, null, $now);
    }

    public static function revocation(User $student, string $itemCode, User $teacher, ?string $comment, \DateTimeImmutable $now): self
    {
        return new self($student, $itemCode, false, ValidationSource::Teacher, $now, self::cleanComment($comment), $teacher, null, $now);
    }

    private static function cleanComment(?string $comment): ?string
    {
        $comment = null === $comment ? '' : trim(mb_substr($comment, 0, 1000));

        return '' === $comment ? null : $comment;
    }

    public function toEvent(): ValidationEvent
    {
        return new ValidationEvent($this->itemCode, $this->granted, $this->validatedOn, $this->recordedAt, $this->source, $this->comment, $this->id ?? 0);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStudent(): User
    {
        return $this->student;
    }

    public function getItemCode(): string
    {
        return $this->itemCode;
    }

    public function isGranted(): bool
    {
        return $this->granted;
    }

    public function getSource(): ValidationSource
    {
        return $this->source;
    }

    public function getValidatedOn(): \DateTimeImmutable
    {
        return $this->validatedOn;
    }

    public function getRecordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function getAttemptId(): ?Uuid
    {
        return $this->attemptId;
    }
}
