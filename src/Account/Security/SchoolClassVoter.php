<?php

declare(strict_types=1);

namespace App\Account\Security;

use App\Account\Entity\SchoolClass;
use App\Account\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * S7 : un enseignant ne gère que ses propres classes (prépare l'arrivée d'autres enseignants).
 *
 * @extends Voter<string, SchoolClass>
 */
final class SchoolClassVoter extends Voter
{
    public const MANAGE = 'CLASS_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE === $attribute && $subject instanceof SchoolClass;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $user->isTeacher() && $subject->getTeacher()->getId() === $user->getId();
    }
}
