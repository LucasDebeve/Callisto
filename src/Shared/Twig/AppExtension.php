<?php

declare(strict_types=1);

namespace App\Shared\Twig;

use App\Shared\Http\CspNonce;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;
use Twig\Markup;

/**
 * Fonctions Twig transverses : nonce CSP et icônes Lucide intégrées (jeu d'icônes unique, aucun appel externe).
 */
final class AppExtension
{
    /** @var array<string, string> */
    private array $icons = [];

    public function __construct(private readonly CspNonce $nonce, #[Autowire('%kernel.project_dir%')] private readonly string $projectDir)
    {
    }

    #[AsTwigFunction('csp_nonce')]
    public function cspNonce(): string
    {
        return $this->nonce->get();
    }

    #[AsTwigFunction('icon', isSafe: ['html'])]
    public function icon(string $name, string $class = 'icon', ?string $label = null): Markup
    {
        if (!preg_match('/^[a-z0-9-]+$/', $name)) {
            throw new \InvalidArgumentException('Nom d\'icône invalide.');
        }
        $svg = $this->icons[$name] ??= (string) @file_get_contents($this->projectDir.'/templates/icons/'.$name.'.svg');
        if ('' === $svg) {
            throw new \InvalidArgumentException(\sprintf('Icône inconnue : %s.', $name));
        }
        $attributes = ' class="'.htmlspecialchars($class, \ENT_QUOTES).'"';
        if (null !== $label) {
            $svg = str_replace(' aria-hidden="true"', ' role="img" aria-label="'.htmlspecialchars($label, \ENT_QUOTES).'"', $svg);
        }

        return new Markup((string) preg_replace('/<svg /', '<svg'.$attributes.' ', $svg, 1), 'UTF-8');
    }

    /**
     * Découpe un intitulé en lignes courtes pour les étiquettes SVG des étoiles.
     *
     * @return list<string>
     */
    #[AsTwigFilter('label_lines')]
    public function labelLines(string $text, int $width = 24, int $maxLines = 2): array
    {
        $lines = explode("\n", wordwrap($text, $width, "\n", true));
        if (\count($lines) > $maxLines) {
            $lines = \array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = rtrim(mb_substr($lines[$maxLines - 1], 0, $width - 1)).'…';
        }

        return array_values($lines);
    }

    /** Pourcentage arrondi, sans division par zéro. */
    #[AsTwigFilter('percent')]
    public function percent(int $value, int $total): int
    {
        return $total > 0 ? (int) round(100 * $value / $total) : 0;
    }
}
