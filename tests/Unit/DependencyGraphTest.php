<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Referential\Domain\ConstellationLayout;
use App\Referential\Domain\DependencyGraph;
use PHPUnit\Framework\TestCase;

final class DependencyGraphTest extends TestCase
{
    public function testDetectsCycle(): void
    {
        $graph = new DependencyGraph(['a' => ['c'], 'b' => ['a'], 'c' => ['b'], 'd' => []]);
        $cycle = $graph->findCycle();

        self::assertNotNull($cycle);
        self::assertSame($cycle[0], $cycle[\count($cycle) - 1]);
        self::assertCount(4, $cycle);
    }

    public function testAcyclicGraphHasNoCycle(): void
    {
        $graph = new DependencyGraph(['a' => [], 'b' => ['a'], 'c' => ['a', 'b']]);

        self::assertNull($graph->findCycle());
        self::assertSame(['a' => 0, 'b' => 1, 'c' => 2], $graph->layers());
        self::assertSame(['b', 'c'], $graph->successorsOf('a'));
    }

    public function testReportsUnknownPrerequisites(): void
    {
        $graph = new DependencyGraph(['a' => ['x'], 'b' => ['a']]);

        self::assertSame([['a', 'x']], $graph->unknownPrerequisites());
    }

    public function testRestrictionIgnoresExternalPrerequisitesInLayers(): void
    {
        $graph = (new DependencyGraph(['ext' => [], 'a' => ['ext'], 'b' => ['a']]))->restrictedTo(['a', 'b']);

        self::assertSame(['a' => 0, 'b' => 1], $graph->layers());
        self::assertSame(['ext'], $graph->prerequisitesOf('a'));
    }

    public function testLayoutGoesLeftToRightAndHonoursManualPositions(): void
    {
        $graph = new DependencyGraph(['a' => [], 'b' => ['a'], 'c' => ['a']]);
        $layout = new ConstellationLayout();
        $positions = $layout->compute($graph, ['a', 'b', 'c']);

        self::assertLessThan($positions['b']['x'], $positions['a']['x']);
        self::assertSame($positions['b']['x'], $positions['c']['x']);
        self::assertNotSame($positions['b']['y'], $positions['c']['y']);

        $manual = $layout->compute($graph, ['a', 'b', 'c'], ['c' => ['x' => 3.0, 'y' => 0.0]]);
        self::assertEqualsWithDelta(ConstellationLayout::MARGIN + 3 * ConstellationLayout::COLUMN_WIDTH, $manual['c']['x'], 0.001);
    }
}
