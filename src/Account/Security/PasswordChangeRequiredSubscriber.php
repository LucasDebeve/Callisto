<?php

declare(strict_types=1);

namespace App\Account\Security;

use App\Account\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * EE1 / S2 : tant que le mot de passe provisoire n'est pas changé, seul le formulaire de changement est accessible.
 */
final readonly class PasswordChangeRequiredSubscriber
{
    private const ALLOWED_ROUTES = ['app_first_password', 'app_logout', 'app_login', 'legal_notice', 'health'];

    public function __construct(private Security $security, private UrlGeneratorInterface $urls)
    {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->isStudent() || !$user->mustChangePassword()) {
            return;
        }
        $route = (string) $event->getRequest()->attributes->get('_route');
        if (\in_array($route, self::ALLOWED_ROUTES, true) || str_starts_with($route, '_')) {
            return;
        }
        $event->setResponse(new RedirectResponse($this->urls->generate('app_first_password')));
    }
}
