<?php

declare(strict_types=1);

namespace App\Account\Security;

use App\Account\Entity\UserKind;

/** Ne charge que des comptes enseignants (pare-feu de l'espace enseignant). */
final class TeacherUserProvider extends KindUserProvider
{
    protected function kind(): UserKind
    {
        return UserKind::Teacher;
    }
}
