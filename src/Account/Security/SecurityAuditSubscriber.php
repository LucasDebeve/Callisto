<?php

declare(strict_types=1);

namespace App\Account\Security;

use App\Account\Entity\User;
use App\Administration\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Trace dans le journal d'audit les connexions, échecs et déconnexions (EP7).
 */
final readonly class SecurityAuditSubscriber
{
    public function __construct(
        private AuditLogger $audit,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {
    }

    #[AsEventListener]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof User) {
            $user->recordLogin($this->clock->now());
            $this->em->flush();
        }
        $this->audit->log(AuditLogger::LOGIN_SUCCESS, $user->getUserIdentifier(), null, ['espace' => $event->getFirewallName()]);
    }

    #[AsEventListener]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $username = $event->getRequest()->attributes->get(SecurityRequestAttributes::LAST_USERNAME);
        $this->audit->log(AuditLogger::LOGIN_FAILURE, \is_string($username) ? $username : null, null, [
            'espace' => $event->getFirewallName(),
            'motif' => $event->getException()->getMessageKey(),
        ]);
    }

    #[AsEventListener(event: TwoFactorAuthenticationEvents::FAILURE)]
    public function onTwoFactorFailure(TwoFactorAuthenticationEvent $event): void
    {
        $this->audit->log(AuditLogger::TWO_FACTOR_FAILURE, $event->getToken()->getUserIdentifier());
    }

    #[AsEventListener]
    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();
        if (null !== $user) {
            $this->audit->log(AuditLogger::LOGOUT, $user->getUserIdentifier());
        }
    }
}
