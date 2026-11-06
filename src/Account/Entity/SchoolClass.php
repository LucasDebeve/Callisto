<?php

declare(strict_types=1);

namespace App\Account\Entity;

use App\Account\Repository\SchoolClassRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Classe d'élèves, rattachée à un enseignant (permet d'en ajouter d'autres plus tard).
 */
#[ORM\Entity(repositoryClass: SchoolClassRepository::class)]
#[ORM\Table(name: 'account_school_class')]
#[ORM\UniqueConstraint(columns: ['name', 'school_year'])]
class SchoolClass
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private string $name;

    #[ORM\Column(length: 9)]
    private string $schoolYear;

    /** Code du niveau du référentiel suivi par la classe (snt, premiere, terminale). */
    #[ORM\Column(length: 32)]
    private string $levelCode;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $teacher;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    /** @var Collection<int, User> */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'schoolClass')]
    private Collection $students;

    public function __construct(string $name, string $schoolYear, string $levelCode, User $teacher)
    {
        $this->name = $name;
        $this->schoolYear = $schoolYear;
        $this->levelCode = $levelCode;
        $this->teacher = $teacher;
        $this->students = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSchoolYear(): string
    {
        return $this->schoolYear;
    }

    public function getLevelCode(): string
    {
        return $this->levelCode;
    }

    public function getTeacher(): User
    {
        return $this->teacher;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function archive(\DateTimeImmutable $at): void
    {
        $this->archivedAt ??= $at;
    }

    /** @return list<User> */
    public function getStudents(): array
    {
        $students = array_values($this->students->toArray());
        usort($students, static fn (User $a, User $b) => [$a->getDisplayName(), $a->getUsername()] <=> [$b->getDisplayName(), $b->getUsername()]);

        return $students;
    }

    /** Année scolaire courante, au format 2026-2027 (bascule au 1er août). */
    public static function currentSchoolYear(\DateTimeImmutable $now): string
    {
        $year = (int) $now->format('Y');
        $start = (int) $now->format('n') >= 8 ? $year : $year - 1;

        return \sprintf('%d-%d', $start, $start + 1);
    }
}
