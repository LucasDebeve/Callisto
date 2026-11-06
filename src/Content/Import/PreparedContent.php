<?php

declare(strict_types=1);

namespace App\Content\Import;

/**
 * Contenu lu, validé et rendu, prêt à être publié. Structure interne à l'import.
 */
final class PreparedContent
{
    /** @var array<string, array{title: string, position: int}> */
    public array $levels = [];

    /** @var array<string, array{level: string, title: string, description: ?string, position: int, file: string}> */
    public array $themes = [];

    /** @var array<string, array{theme: string, title: string, position: int, file: string, dir: string}> */
    public array $chapters = [];

    /** @var array<string, array{chapter: string, type: string, title: string, description: ?string, threshold: int, questionsPerAttempt: int, prerequisites: list<string>, layout: ?array{x: float, y: float}, position: int, file: string}> */
    public array $items = [];

    /** @var array<string, array{chapter: string, title: string, position: int, items: list<string>, markdown: string, html: string, toc: list<array{level: int, id: string, text: string}>, file: string}> */
    public array $courses = [];

    /** @var array<string, array{chapter: string, title: string, position: int, items: list<string>, published: bool, statement: string, solution: ?string, statementHtml: string, solutionHtml: ?string, file: string}> */
    public array $exercises = [];

    /** @var array<string, array{item: string, type: string, prompt: string, code: ?string, language: string, choices: list<array{texte: string, juste: bool}>, accepted: list<string>, explanation: ?string, promptHtml: string, codeHtml: ?string, choicesHtml: list<array{key: string, html: string, correct: bool}>, explanationHtml: ?string, file: string}> */
    public array $questions = [];

    /** @var array<string, array{absolute: string, chapter: string}> chemin relatif => fichier */
    public array $files = [];

    public ?string $revision = null;
}
