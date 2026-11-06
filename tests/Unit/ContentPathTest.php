<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Content\Import\ContentLoader;
use PHPUnit\Framework\TestCase;

final class ContentPathTest extends TestCase
{
    public function testRelativePathsAlwaysUseForwardSlashes(): void
    {
        self::assertSame(
            'snt/01-internet/01-protocoles/fichiers/reseau-local.svg',
            ContentLoader::relativePath('C:\\Users\\prof\\Callisto\\content', 'C:\\Users\\prof\\Callisto\\content\\snt\\01-internet\\01-protocoles/fichiers\\reseau-local.svg'),
        );
        self::assertSame(
            'snt/01-internet/01-protocoles',
            ContentLoader::relativePath('C:/Users/prof/Callisto/content/', 'C:/Users/prof/Callisto/content\\snt\\01-internet\\01-protocoles'),
        );
        self::assertSame('snt/theme.yaml', ContentLoader::relativePath('/srv/contenus', '/srv/contenus/snt/theme.yaml'));
    }
}
