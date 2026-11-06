<?php

declare(strict_types=1);

namespace App\Account\Security;

use App\Account\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Refuse les comptes désactivés ou d'une classe archivée, et tout compte enseignant sans TOTP (S5).
 */
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }
        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Identifiant ou mot de passe incorrect.');
        }
        if ($user->isTeacher() && !$user->hasTotpSecret()) {
            throw new CustomUserMessageAccountStatusException('Ce compte n\'a pas de double authentification configurée. Voir le guide d\'exploitation.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
