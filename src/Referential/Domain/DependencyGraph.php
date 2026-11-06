<?php

declare(strict_types=1);

namespace App\Referential\Domain;

/**
 * Graphe orienté des dépendances du référentiel : une arête va d'un prérequis vers l'élément qui en dépend.
 *
 * Classe sans lien avec le framework (M2).
 */
final class DependencyGraph
{
    /** @var array<string, list<string>> code => prérequis */
    private array $prerequisites = [];

    /**
     * @param array<string, list<string>> $prerequisites code => liste des codes prérequis
     */
    public function __construct(array $prerequisites)
    {
        foreach ($prerequisites as $code => $list) {
            $this->prerequisites[(string) $code] = array_values(array_unique($list));
        }
    }

    /** @return list<string> */
    public function nodes(): array
    {
        return array_map('strval', array_keys($this->prerequisites));
    }

    public function has(string $code): bool
    {
        return isset($this->prerequisites[$code]);
    }

    /** @return list<string> */
    public function prerequisitesOf(string $code): array
    {
        return $this->prerequisites[$code] ?? [];
    }

    /** @return list<string> */
    public function successorsOf(string $code): array
    {
        $result = [];
        foreach ($this->prerequisites as $node => $list) {
            if (\in_array($code, $list, true)) {
                $result[] = (string) $node;
            }
        }

        return $result;
    }

    /**
     * Prérequis qui ne sont pas des nœuds du graphe.
     *
     * @return list<array{0: string, 1: string}> paires [élément, prérequis inconnu]
     */
    public function unknownPrerequisites(): array
    {
        $unknown = [];
        foreach ($this->prerequisites as $node => $list) {
            foreach ($list as $prerequisite) {
                if (!isset($this->prerequisites[$prerequisite])) {
                    $unknown[] = [(string) $node, $prerequisite];
                }
            }
        }

        return $unknown;
    }

    /**
     * Renvoie un cycle (liste de codes, le premier répété à la fin) ou null si le graphe est acyclique.
     *
     * @return list<string>|null
     */
    public function findCycle(): ?array
    {
        $state = []; // 1 = en cours, 2 = terminé
        $stack = [];

        $visit = function (string $node) use (&$visit, &$state, &$stack): ?array {
            $state[$node] = 1;
            $stack[] = $node;
            foreach ($this->prerequisitesOf($node) as $next) {
                if (!isset($this->prerequisites[$next])) {
                    continue;
                }
                if (1 === ($state[$next] ?? 0)) {
                    $start = array_search($next, $stack, true);
                    $cycle = \array_slice($stack, (int) $start);
                    $cycle[] = $next;

                    return array_reverse($cycle);
                }
                if (!isset($state[$next])) {
                    $found = $visit($next);
                    if (null !== $found) {
                        return $found;
                    }
                }
            }
            array_pop($stack);
            $state[$node] = 2;

            return null;
        };

        foreach ($this->nodes() as $node) {
            if (!isset($state[$node])) {
                $found = $visit($node);
                if (null !== $found) {
                    return array_values(array_map('strval', $found));
                }
            }
        }

        return null;
    }

    /**
     * Rang de chaque nœud : longueur du plus long chemin depuis un nœud sans prérequis (graphe supposé acyclique).
     * Les prérequis situés hors du graphe (autre thème) sont ignorés.
     *
     * @return array<string, int>
     */
    public function layers(): array
    {
        $layers = [];
        $compute = function (string $node, array $path) use (&$compute, &$layers): int {
            if (isset($layers[$node])) {
                return $layers[$node];
            }
            if (isset($path[$node])) {
                throw new \LogicException(\sprintf('Cycle détecté autour de « %s ».', $node));
            }
            $path[$node] = true;
            $layer = 0;
            foreach ($this->prerequisitesOf($node) as $prerequisite) {
                if (isset($this->prerequisites[$prerequisite])) {
                    $layer = max($layer, $compute($prerequisite, $path) + 1);
                }
            }

            return $layers[$node] = $layer;
        };

        foreach ($this->nodes() as $node) {
            $compute($node, []);
        }

        return $layers;
    }

    /**
     * Sous-graphe restreint à certains codes (les prérequis externes sont conservés dans les listes).
     *
     * @param list<string> $codes
     */
    public function restrictedTo(array $codes): self
    {
        $keep = array_flip($codes);

        return new self(array_intersect_key($this->prerequisites, $keep));
    }
}
