<?php

declare(strict_types=1);

namespace App\Quiz\Security;

use App\Account\Entity\User;
use App\Quiz\Entity\Attempt;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * S7 / S10 : une tentative n'est lisible et modifiable que par l'élève qui l'a commencée.
 *
 * @extends Voter<string, Attempt>
 */
final class AttemptVoter extends Voter
{
    public const OWN = 'ATTEMPT_OWN';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::OWN === $attribute && $subject instanceof Attempt;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $user->isStudent() && $subject->isOwnedBy($user);
    }
}
