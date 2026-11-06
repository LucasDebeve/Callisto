<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Progress\Domain\GradeCalculator;
use App\Progress\Domain\ItemStatus;
use App\Progress\Domain\ProgressLedger;
use App\Progress\Domain\ValidationEvent;
use App\Progress\Domain\ValidationSource;
use App\Referential\Domain\DependencyGraph;
use PHPUnit\Framework\TestCase;

final class ProgressLedgerTest extends TestCase
{
    private function event(string $item, bool $granted, string $at, int $seq = 0, ?string $comment = null): ValidationEvent
    {
        $date = new \DateTimeImmutable($at);

        return new ValidationEvent($item, $granted, $date, $date, ValidationSource::Teacher, $comment, $seq);
    }

    public function testCurrentStateIsTheLastRecord(): void
    {
        $ledger = new ProgressLedger([
            $this->event('a', true, '2026-10-01'),
            $this->event('a', false, '2026-10-02'),
            $this->event('b', true, '2026-10-01'),
        ]);

        self::assertFalse($ledger->isValidated('a'));
        self::assertTrue($ledger->isValidated('b'));
        self::assertFalse($ledger->isValidated('inconnu'));
    }

    public function testOrderOfInputDoesNotMatterAndSequenceBreaksTies(): void
    {
        $ledger = new ProgressLedger([
            $this->event('a', false, '2026-10-02 10:00', 2),
            $this->event('a', true, '2026-10-02 10:00', 1),
            $this->event('a', true, '2026-10-03', 3, 'Revalidé'),
        ]);

        $state = $ledger->stateOf('a');
        self::assertTrue($state->validated);
        self::assertSame('Revalidé', $state->comment);
        self::assertSame('2026-10-03', $state->validatedOn?->format('Y-m-d'));
    }

    public function testStatusesAndNextSteps(): void
    {
        $graph = new DependencyGraph(['a' => [], 'b' => ['a'], 'c' => ['b'], 'd' => []]);
        $ledger = new ProgressLedger([$this->event('a', true, '2026-10-01')]);

        self::assertSame(ItemStatus::Validated, $ledger->statusOf('a', $graph));
        self::assertSame(ItemStatus::Available, $ledger->statusOf('b', $graph));
        self::assertSame(ItemStatus::Locked, $ledger->statusOf('c', $graph));
        self::assertSame(['d', 'b'], $ledger->nextSteps($graph, ['a', 'b', 'c', 'd']));
        self::assertSame(['d'], $ledger->nextSteps($graph, ['a', 'b', 'c', 'd'], 1));
    }

    public function testGradeIsWeightedByLevelAndIgnoresEmptyLevels(): void
    {
        $calculator = new GradeCalculator(['savoir' => 1.0, 'savoir-faire' => 1.0, 'competence' => 2.0]);

        self::assertSame(10.0, $calculator->compute([
            'savoir' => ['validated' => 2, 'total' => 4],
            'savoir-faire' => ['validated' => 0, 'total' => 0],
            'competence' => ['validated' => 1, 'total' => 2],
        ]));
        self::assertNull($calculator->compute(['savoir' => ['validated' => 0, 'total' => 0]]));
    }
}
