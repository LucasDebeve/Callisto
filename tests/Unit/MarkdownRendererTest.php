<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Content\Markdown\InternalLink;
use App\Content\Markdown\LinkResolver;
use App\Content\Markdown\MarkdownRenderer;
use PHPUnit\Framework\TestCase;

final class MarkdownRendererTest extends TestCase
{
    public function testRawHtmlIsReportedAndEscaped(): void
    {
        $result = (new MarkdownRenderer())->render("Texte <script>alert(1)</script>\n\n<iframe src=x></iframe>");

        self::assertCount(3, $result->errors);
        self::assertStringNotContainsString('<script', $result->html);
        self::assertStringNotContainsString('<iframe', $result->html);
    }

    public function testDangerousLinksAreNeutralised(): void
    {
        $result = (new MarkdownRenderer())->render('[x](javascript:alert(1)) [y](https://example.org)');

        self::assertStringNotContainsString('javascript:', $result->html);
        self::assertStringContainsString('href="https://example.org"', $result->html);
        self::assertNotEmpty($result->errors);
    }

    public function testInternalLinksCodeHighlightingAndToc(): void
    {
        $resolver = new class implements LinkResolver {
            public function resolve(InternalLink $link): ?string
            {
                return 'cours' === $link->scheme && 'ok' === $link->target ? '/cours/ok' : null;
            }
        };
        $result = (new MarkdownRenderer())->render("## Titre\n\n[a](cours:ok) [b](cours:absent)\n\n```python\nprint(1)\n```", $resolver);

        self::assertStringContainsString('href="/cours/ok"', $result->html);
        self::assertSame(['Lien interne invalide : « cours:absent ».'], $result->errors);
        self::assertStringContainsString('class="hljs language-python"', $result->html);
        self::assertSame([['level' => 2, 'id' => 'titre', 'text' => 'Titre']], $result->toc);
    }
}
