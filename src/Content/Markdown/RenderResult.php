<?php

declare(strict_types=1);

namespace App\Content\Markdown;

final readonly class RenderResult
{
    /**
     * @param list<array{level: int, id: string, text: string}> $toc
     * @param list<string>                                      $errors
     */
    public function __construct(
        public string $html,
        public array $toc,
        public array $errors,
    ) {
    }
}
