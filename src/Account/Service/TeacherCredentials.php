<?php

declare(strict_types=1);

namespace App\Account\Service;

use App\Account\Entity\User;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Enrôlement TOTP de l'enseignant et génération des codes de secours (stockés hachés, affichés une fois).
 */
final readonly class TeacherCredentials
{
    public const BACKUP_CODES = 8;

    public function __construct(private TotpAuthenticatorInterface $totp)
    {
    }

    /**
     * @return array{0: string, 1: list<string>} URI otpauth et codes de secours en clair
     */
    public function enroll(User $teacher): array
    {
        $teacher->setTotpSecret($this->totp->generateSecret());
        $codes = [];
        for ($i = 0; $i < self::BACKUP_CODES; ++$i) {
            $codes[] = implode('-', str_split(strtoupper(bin2hex(random_bytes(5))), 5));
        }
        $teacher->setBackupCodes(array_map(static fn (string $c) => password_hash($c, \PASSWORD_DEFAULT), $codes));

        return [$this->totp->getQRContent($teacher), $codes];
    }

    /**
     * @param list<string> $codes
     */
    public function printEnrollment(SymfonyStyle $io, string $uri, array $codes): void
    {
        $io->success('Double authentification configurée.');
        $io->text('Ajoute ce compte dans ton application TOTP (saisie manuelle de l\'URI ou conversion en QR code hors ligne) :');
        $io->writeln('  '.$uri);
        $io->newLine();
        $io->text('Codes de secours, à usage unique. Conserve-les hors de l\'application (papier, coffre de mots de passe) :');
        $io->listing($codes);
        $io->warning('Ces informations ne seront plus jamais affichées.');
    }
}
