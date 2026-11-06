<?php

declare(strict_types=1);

namespace App\Account\Security;

use App\Account\Entity\UserKind;

/** Ne charge que des comptes élèves (pare-feu principal). */
final class StudentUserProvider extends KindUserProvider
{
    protected function kind(): UserKind
    {
        return UserKind::Student;
    }
}
