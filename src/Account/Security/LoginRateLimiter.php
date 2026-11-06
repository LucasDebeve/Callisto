<?php

declare(strict_types=1);

namespace App\Account\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RateLimiter\AbstractRequestRateLimiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * S3 : limite les essais de connexion à la fois par adresse IP et par compte (quelle que soit l'IP),
 * avec blocage temporaire. Les compteurs sont remis à zéro après une connexion réussie.
 */
final class LoginRateLimiter extends AbstractRequestRateLimiter
{
    public function __construct(
        #[Autowire(service: 'limiter.login_per_ip')]
        private readonly RateLimiterFactoryInterface $perIp,
        #[Autowire(service: 'limiter.login_per_account')]
        private readonly RateLimiterFactoryInterface $perAccount,
    ) {
    }

    protected function getLimiters(Request $request): array
    {
        $username = $request->attributes->getString(SecurityRequestAttributes::LAST_USERNAME);
        $username = preg_match('//u', $username) ? mb_strtolower($username, 'UTF-8') : strtolower($username);

        return [
            $this->perIp->create('ip-'.$request->getClientIp()),
            $this->perAccount->create('account-'.$username),
        ];
    }
}
