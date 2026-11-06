<?php

declare(strict_types=1);

namespace App\Progress\Service;

use App\Account\Entity\User;
use App\Administration\AuditLogger;
use App\Progress\Domain\ProgressLedger;
use App\Progress\Entity\Validation;
use App\Progress\Repository\ValidationRepository;
use App\Referential\Domain\ItemType;
use App\Referential\Service\ReferentialCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Seul point d'écriture des validations. Chaque appel ajoute un enregistrement, jamais de modification (S11).
 */
final readonly class ProgressRecorder
{
    public function __construct(
        private ValidationRepository $validations,
        private EntityManagerInterface $em,
        private ReferentialCatalog $referential,
        private ClockInterface $clock,
        private AuditLogger $audit,
    ) {
    }

    public function ledgerFor(User $student): ProgressLedger
    {
        return new ProgressLedger(array_map(static fn (Validation $v) => $v->toEvent(), $this->validations->findForStudent($student)));
    }

    /**
     * EQ3 : la réussite d'un QCM crée la validation du savoir, une seule fois.
     * Renvoie vrai si une nouvelle validation a été créée.
     */
    public function grantFromQuiz(User $student, string $itemCode, Uuid $attemptId): bool
    {
        $item = $this->referential->item($itemCode);
        if (null === $item || ItemType::Knowledge !== $item->getType() || $this->ledgerFor($student)->isValidated($itemCode)) {
            return false;
        }
        $this->validations->add(Validation::fromQuiz($student, $itemCode, $attemptId, $this->clock->now()));
        $this->em->flush();
        $this->audit->log(AuditLogger::QUIZ_VALIDATION, $student->getUserIdentifier(), $itemCode);

        return true;
    }

    /**
     * Saisie enseignant (EP3). Une nouvelle saisie sur un élément déjà validé ajoute un enregistrement
     * qui remplace la date et le commentaire affichés.
     */
    public function grantByTeacher(User $teacher, User $student, string $itemCode, \DateTimeImmutable $validatedOn, ?string $comment): Validation
    {
        $this->assertTeacherAndStudent($teacher, $student, $itemCode);
        $validation = Validation::byTeacher($student, $itemCode, $teacher, $validatedOn, $comment, $this->clock->now());
        $this->validations->add($validation);
        $this->em->flush();
        $this->audit->log(AuditLogger::VALIDATION_GRANTED, $teacher->getUserIdentifier(), $student->getUserIdentifier(), [
            'element' => $itemCode,
            'date' => $validatedOn->format('Y-m-d'),
        ]);

        return $validation;
    }

    /** Retrait d'une validation saisie par erreur : lui-même enregistré et daté. */
    public function revoke(User $teacher, User $student, string $itemCode, ?string $comment = null): ?Validation
    {
        $this->assertTeacherAndStudent($teacher, $student, $itemCode);
        if (!$this->ledgerFor($student)->isValidated($itemCode)) {
            return null;
        }
        $validation = Validation::revocation($student, $itemCode, $teacher, $comment, $this->clock->now());
        $this->validations->add($validation);
        $this->em->flush();
        $this->audit->log(AuditLogger::VALIDATION_REVOKED, $teacher->getUserIdentifier(), $student->getUserIdentifier(), ['element' => $itemCode]);

        return $validation;
    }

    private function assertTeacherAndStudent(User $teacher, User $student, string $itemCode): void
    {
        if (!$teacher->isTeacher() || !$student->isStudent()) {
            throw new \DomainException('Seul un enseignant peut saisir une validation pour un élève.');
        }
        if (null === $this->referential->item($itemCode)) {
            throw new \DomainException(\sprintf('Élément « %s » inconnu.', $itemCode));
        }
    }
}
