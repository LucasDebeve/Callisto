<?php

declare(strict_types=1);

namespace App\Shared\Http;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Nonce CSP par requête. Il n'est posé que sur la carte d'import générée par AssetMapper :
 * aucun script en ligne n'est écrit à la main (voir docs/adr/0004-csp.md).
 */
final class CspNonce implements ResetInterface
{
    private ?string $nonce = null;

    public function get(): string
    {
        return $this->nonce ??= rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }

    public function isUsed(): bool
    {
        return null !== $this->nonce;
    }

    public function reset(): void
    {
        $this->nonce = null;
    }
}
