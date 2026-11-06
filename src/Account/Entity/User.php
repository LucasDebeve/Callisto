<?php

declare(strict_types=1);

namespace App\Account\Entity;

use App\Account\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Compte local. Données réduites au minimum (R1) : identifiant, prénom et initiale, classe.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'account_user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface, BackupCodeInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $username;

    #[ORM\Column(length: 80)]
    private string $displayName;

    #[ORM\Column(length: 16, enumType: UserKind::class)]
    private UserKind $kind;

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column]
    private bool $mustChangePassword = true;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\ManyToOne(targetEntity: SchoolClass::class, inversedBy: 'students')]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    private ?SchoolClass $schoolClass = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $totpSecret = null;

    /** @var list<string> codes de secours hachés */
    #[ORM\Column(type: Types::JSON)]
    private array $backupCodes = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    public function __construct(string $username, string $displayName, UserKind $kind, \DateTimeImmutable $createdAt = new \DateTimeImmutable())
    {
        if ('' === $username) {
            throw new \InvalidArgumentException('Identifiant vide.');
        }
        $this->username = $username;
        $this->displayName = $displayName;
        $this->kind = $kind;
        $this->createdAt = $createdAt;
        $this->mustChangePassword = UserKind::Student === $kind;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getUserIdentifier(): string
    {
        return '' !== $this->username ? $this->username : throw new \LogicException('Identifiant vide.');
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function getKind(): UserKind
    {
        return $this->kind;
    }

    public function isTeacher(): bool
    {
        return UserKind::Teacher === $this->kind;
    }

    public function isStudent(): bool
    {
        return UserKind::Student === $this->kind;
    }

    public function getRoles(): array
    {
        return [$this->isTeacher() ? 'ROLE_TEACHER' : 'ROLE_STUDENT'];
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword, bool $provisional): void
    {
        $this->password = $hashedPassword;
        $this->mustChangePassword = $provisional;
    }

    public function mustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    public function isActive(): bool
    {
        return $this->active && !($this->schoolClass?->isArchived() ?? false);
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function getSchoolClass(): ?SchoolClass
    {
        return $this->schoolClass;
    }

    public function setSchoolClass(?SchoolClass $schoolClass): void
    {
        $this->schoolClass = $schoolClass;
    }

    public function getLevelCode(): ?string
    {
        return $this->schoolClass?->getLevelCode();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function recordLogin(\DateTimeImmutable $at): void
    {
        $this->lastLoginAt = $at;
    }

    public function eraseCredentials(): void
    {
    }

    // --- Double authentification (S5) -------------------------------------------------

    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->isTeacher() && null !== $this->totpSecret;
    }

    public function hasTotpSecret(): bool
    {
        return null !== $this->totpSecret;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->username;
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        return null === $this->totpSecret ? null : new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    public function setTotpSecret(?string $secret): void
    {
        $this->totpSecret = $secret;
    }

    /**
     * @param list<string> $hashedCodes
     */
    public function setBackupCodes(array $hashedCodes): void
    {
        $this->backupCodes = $hashedCodes;
    }

    public function isBackupCode(string $code): bool
    {
        $code = strtoupper(trim($code));
        foreach ($this->backupCodes as $hash) {
            if (password_verify($code, $hash)) {
                return true;
            }
        }

        return false;
    }

    public function invalidateBackupCode(string $code): void
    {
        $code = strtoupper(trim($code));
        $this->backupCodes = array_values(array_filter($this->backupCodes, static fn (string $hash) => !password_verify($code, $hash)));
    }

    public function countBackupCodes(): int
    {
        return \count($this->backupCodes);
    }
}
