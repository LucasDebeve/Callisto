<?php

declare(strict_types=1);

namespace App\Progress\Service;

use App\Account\Entity\User;
use App\Progress\Entity\Validation;
use App\Progress\Repository\ValidationRepository;
use App\Quiz\Entity\Attempt;
use App\Quiz\Repository\AttemptRepository;
use App\Referential\Entity\Item;
use App\Referential\Service\ReferentialCatalog;

/**
 * Historique d'un élève (EE4) : tentatives de QCM et validations, du plus récent au plus ancien.
 * Les éléments archivés restent visibles (EC5).
 *
 * @phpstan-type Entry array{kind: 'tentative'|'validation'|'retrait', at: \DateTimeImmutable, item: ?Item, itemCode: string, attempt: ?Attempt, validation: ?Validation}
 */
final readonly class HistoryBuilder
{
    public function __construct(
        private ValidationRepository $validations,
        private AttemptRepository $attempts,
        private ReferentialCatalog $referential,
    ) {
    }

    /** @return list<Entry> */
    public function timeline(User $student): array
    {
        $entries = [];
        foreach ($this->attempts->findSubmittedFor($student) as $attempt) {
            $entries[] = [
                'kind' => 'tentative',
                'at' => $attempt->getSubmittedAt() ?? $attempt->getStartedAt(),
                'item' => $this->referential->item($attempt->getItemCode()),
                'itemCode' => $attempt->getItemCode(),
                'attempt' => $attempt,
                'validation' => null,
            ];
        }
        foreach ($this->validations->findForStudent($student) as $validation) {
            $entries[] = [
                'kind' => $validation->isGranted() ? 'validation' : 'retrait',
                'at' => $validation->getRecordedAt(),
                'item' => $this->referential->item($validation->getItemCode()),
                'itemCode' => $validation->getItemCode(),
                'attempt' => null,
                'validation' => $validation,
            ];
        }
        usort($entries, static fn (array $a, array $b) => [$b['at'], 'tentative' === $b['kind'] ? 0 : 1] <=> [$a['at'], 'tentative' === $a['kind'] ? 0 : 1]);

        return $entries;
    }

    /** @return array<string, list<Entry>> code de l'élément => entrées */
    public function perItem(User $student): array
    {
        $result = [];
        foreach ($this->timeline($student) as $entry) {
            $result[$entry['itemCode']][] = $entry;
        }

        return $result;
    }
}
