<?php

declare(strict_types=1);

namespace App\Account\Entity;

enum UserKind: string
{
    case Student = 'eleve';
    case Teacher = 'enseignant';
}
