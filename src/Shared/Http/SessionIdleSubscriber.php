<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * S4 : expiration de la session après une période d'inactivité.
 */
final readonly class SessionIdleSubscriber
{
    private const KEY = '_callisto_last_activity';

    public function __construct(
        private Security $security,
        private ClockInterface $clock,
        private UrlGeneratorInterface $urls,
        private int $studentTimeoutMinutes,
        private int $teacherTimeoutMinutes,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->hasPreviousSession()) {
            return;
        }
        $session = $request->getSession();
        $last = $session->get(self::KEY);
        $now = $this->clock->now()->getTimestamp();
        $isTeacherArea = str_starts_with($request->getPathInfo(), '/enseignant');
        $timeout = 60 * ($isTeacherArea ? $this->teacherTimeoutMinutes : $this->studentTimeoutMinutes);

        if (\is_int($last) && $now - $last > $timeout && null !== $this->security->getToken()) {
            $session->invalidate();
            $route = $isTeacherArea ? 'teacher_login' : 'app_login';
            $event->setResponse(new RedirectResponse($this->urls->generate($route, ['expiree' => 1])));

            return;
        }
        $session->set(self::KEY, $now);
    }
}
