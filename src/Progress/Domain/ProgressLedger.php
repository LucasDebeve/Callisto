<?php

declare(strict_types=1);

namespace App\Progress\Domain;

use App\Referential\Domain\DependencyGraph;

/**
 * Registre de progression d'un élève : l'état de chaque élément se déduit du dernier enregistrement
 * (ordre d'enregistrement, puis séquence). Rien n'est jamais effacé.
 */
final class ProgressLedger
{
    /** @var array<string, ItemState> */
    private array $states = [];

    /**
     * @param iterable<ValidationEvent> $events
     */
    public function __construct(iterable $events)
    {
        $list = [...$events];
        usort($list, static fn (ValidationEvent $a, ValidationEvent $b): int => [$a->recordedAt, $a->sequence] <=> [$b->recordedAt, $b->sequence]);

        foreach ($list as $event) {
            $this->states[$event->itemCode] = $event->granted
                ? new ItemState(true, $event->validatedOn, $event->source, $event->comment)
                : ItemState::notValidated();
        }
    }

    public function stateOf(string $itemCode): ItemState
    {
        return $this->states[$itemCode] ?? ItemState::notValidated();
    }

    public function isValidated(string $itemCode): bool
    {
        return $this->stateOf($itemCode)->validated;
    }

    public function statusOf(string $itemCode, DependencyGraph $graph): ItemStatus
    {
        if ($this->isValidated($itemCode)) {
            return ItemStatus::Validated;
        }
        foreach ($graph->prerequisitesOf($itemCode) as $prerequisite) {
            if (!$this->isValidated($prerequisite)) {
                return ItemStatus::Locked;
            }
        }

        return ItemStatus::Available;
    }

    /**
     * @param list<string> $codes
     */
    public function countValidated(array $codes): int
    {
        return \count(array_filter($codes, fn (string $c) => $this->isValidated($c)));
    }

    /**
     * Prochaines étapes (EE3) : éléments non validés dont tous les prérequis le sont,
     * les plus « bas » dans le graphe d'abord, puis dans l'ordre du référentiel.
     *
     * @param list<string> $orderedCodes codes candidats, dans l'ordre du référentiel
     *
     * @return list<string>
     */
    public function nextSteps(DependencyGraph $graph, array $orderedCodes, int $limit = 3): array
    {
        $layers = $graph->layers();
        $candidates = [];
        foreach ($orderedCodes as $index => $code) {
            if (ItemStatus::Available === $this->statusOf($code, $graph)) {
                $candidates[] = [$layers[$code] ?? 0, $index, $code];
            }
        }
        sort($candidates);

        return \array_slice(array_column($candidates, 2), 0, $limit);
    }
}
