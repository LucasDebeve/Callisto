<?php

declare(strict_types=1);

namespace App\Account\Security;

use App\Account\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * S7 : seul l'enseignant de la classe d'un élève peut consulter ou modifier ses données.
 *
 * @extends Voter<string, User>
 */
final class StudentVoter extends Voter
{
    public const MANAGE = 'STUDENT_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE === $attribute && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->isTeacher() || !$subject->isStudent()) {
            return false;
        }

        return $subject->getSchoolClass()?->getTeacher()->getId() === $user->getId();
    }
}
