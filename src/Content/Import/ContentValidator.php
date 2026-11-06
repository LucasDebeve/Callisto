<?php

declare(strict_types=1);

namespace App\Content\Import;

use App\Content\Markdown\InternalLink;
use App\Content\Markdown\LinkResolver;
use App\Content\Markdown\MarkdownRenderer;
use App\Referential\Domain\DependencyGraph;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Contrôles de cohérence du contenu chargé (EC2) et rendu Markdown :
 * références existantes, dépendances sans cycle, au moins une bonne réponse, liens internes valides, aucun HTML brut.
 */
final readonly class ContentValidator
{
    public function __construct(
        private MarkdownRenderer $markdown,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function validate(PreparedContent $c, ImportReport $report): void
    {
        $this->checkStructure($c, $report);
        $this->checkGraph($c, $report);
        $this->checkQuestions($c, $report);
        $this->renderAll($c, $report);

        $report->count('niveaux', \count($c->levels));
        $report->count('thèmes', \count($c->themes));
        $report->count('chapitres', \count($c->chapters));
        $report->count('éléments', \count($c->items));
        $report->count('cours', \count($c->courses));
        $report->count('exercices', \count($c->exercises));
        $report->count('questions', \count($c->questions));
        $report->count('fichiers', \count($c->files));
    }

    private function checkStructure(PreparedContent $c, ImportReport $report): void
    {
        $all = [];
        foreach (['thème' => $c->themes, 'chapitre' => $c->chapters, 'élément' => $c->items] as $label => $list) {
            foreach ($list as $code => $data) {
                if (isset($all[$code])) {
                    $report->error($data['file'], \sprintf('identifiant « %s » déjà utilisé par un %s.', $code, $all[$code]));
                }
                $all[$code] = $label;
            }
        }
        foreach ($c->courses as $code => $course) {
            foreach ($course['items'] as $item) {
                if (!isset($c->items[$item])) {
                    $report->error($course['file'], \sprintf('savoir « %s » inconnu.', $item));
                }
            }
        }
        foreach ($c->exercises as $exercise) {
            foreach ($exercise['items'] as $item) {
                if (!isset($c->items[$item])) {
                    $report->error($exercise['file'], \sprintf('élément « %s » inconnu.', $item));
                }
            }
        }
        foreach ($c->chapters as $code => $chapter) {
            if ([] === array_filter($c->items, static fn (array $i) => $i['chapter'] === $code)) {
                $report->warning($chapter['file'], 'aucun savoir, savoir-faire ni compétence dans ce chapitre.');
            }
        }
    }

    private function checkGraph(PreparedContent $c, ImportReport $report): void
    {
        $map = [];
        foreach ($c->items as $code => $item) {
            $map[$code] = $item['prerequisites'];
            if (\in_array($code, $item['prerequisites'], true)) {
                $report->error($item['file'], \sprintf('« %s » ne peut pas être son propre prérequis.', $code));
            }
        }
        $graph = new DependencyGraph($map);
        foreach ($graph->unknownPrerequisites() as [$item, $missing]) {
            $report->error($c->items[$item]['file'], \sprintf('prérequis « %s » de « %s » inconnu.', $missing, $item));
        }
        $cycle = $graph->findCycle();
        if (null !== $cycle) {
            $report->error('', 'dépendances en cycle : '.implode(' → ', $cycle).'.');
        }
    }

    private function checkQuestions(PreparedContent $c, ImportReport $report): void
    {
        $bank = [];
        foreach ($c->questions as $code => $q) {
            $item = $c->items[$q['item']] ?? null;
            if (null === $item) {
                $report->error($q['file'], \sprintf('savoir « %s » inconnu.', $q['item']));
            } elseif ('savoir' !== $item['type']) {
                $report->error($q['file'], \sprintf('« %s » n\'est pas un savoir : seuls les savoirs se valident par QCM.', $q['item']));
            }
            $bank[$q['item']] = ($bank[$q['item']] ?? 0) + 1;
            $correct = \count(array_filter($q['choices'], static fn (array $ch) => $ch['juste']));

            switch ($q['type']) {
                case 'unique':
                    if (\count($q['choices']) < 2) {
                        $report->error($q['file'], \sprintf('question « %s » : au moins deux choix attendus.', $code));
                    }
                    if (1 !== $correct) {
                        $report->error($q['file'], \sprintf('question « %s » : un choix unique doit avoir exactement une bonne réponse (%d trouvée(s)).', $code, $correct));
                    }
                    break;
                case 'multiple':
                    if (\count($q['choices']) < 2) {
                        $report->error($q['file'], \sprintf('question « %s » : au moins deux choix attendus.', $code));
                    }
                    if ($correct < 1) {
                        $report->error($q['file'], \sprintf('question « %s » : aucune bonne réponse.', $code));
                    }
                    break;
                case 'courte':
                    if ([] === array_filter($q['accepted'], static fn (string $a) => '' !== trim($a))) {
                        $report->error($q['file'], \sprintf('question « %s » : aucune réponse acceptée.', $code));
                    }
                    if ([] !== $q['choices']) {
                        $report->warning($q['file'], \sprintf('question « %s » : les choix sont ignorés pour une réponse courte.', $code));
                    }
                    break;
            }
        }

        foreach ($c->items as $code => $item) {
            if ('savoir' !== $item['type']) {
                continue;
            }
            $n = $bank[$code] ?? 0;
            if (0 === $n) {
                $report->warning($item['file'], \sprintf('savoir « %s » sans question : son QCM ne sera pas disponible.', $code));
            } elseif ($n < $item['questionsPerAttempt']) {
                $report->warning($item['file'], \sprintf('savoir « %s » : %d question(s) dans la banque pour %d par tentative.', $code, $n, $item['questionsPerAttempt']));
            }
        }
    }

    private function renderAll(PreparedContent $c, ImportReport $report): void
    {
        foreach ($c->courses as $code => $course) {
            $result = $this->markdown->render($course['markdown'], $this->resolver($c, $course['chapter']), 1);
            $this->reportErrors($course['file'], $result->errors, $report);
            $c->courses[$code]['html'] = $result->html;
            $c->courses[$code]['toc'] = $result->toc;
        }
        foreach ($c->exercises as $code => $exercise) {
            $resolver = $this->resolver($c, $exercise['chapter']);
            $result = $this->markdown->render($exercise['statement'], $resolver, 1);
            $this->reportErrors($exercise['file'], $result->errors, $report);
            $c->exercises[$code]['statementHtml'] = $result->html;
            if (null !== $exercise['solution']) {
                $solution = $this->markdown->render($exercise['solution'], $resolver, 1);
                $this->reportErrors(substr($exercise['file'], 0, -3).'.corrige.md', $solution->errors, $report);
                $c->exercises[$code]['solutionHtml'] = $solution->html;
            }
        }
        foreach ($c->questions as $code => $q) {
            $chapter = $c->items[$q['item']]['chapter'] ?? '';
            $resolver = $this->resolver($c, $chapter);
            $prompt = $this->markdown->render($q['prompt'], $resolver, 3);
            $this->reportErrors($q['file'], $prompt->errors, $report, $code);
            $c->questions[$code]['promptHtml'] = $prompt->html;
            $c->questions[$code]['codeHtml'] = null === $q['code'] ? null : $this->markdown->renderCode($q['code'], $q['language']);
            $choices = [];
            foreach ($q['choices'] as $index => $choice) {
                $html = $this->markdown->render($choice['texte'], $resolver, 3);
                $this->reportErrors($q['file'], $html->errors, $report, $code);
                $choices[] = ['key' => \chr(97 + $index), 'html' => $html->html, 'correct' => $choice['juste']];
            }
            $c->questions[$code]['choicesHtml'] = $choices;
            if (null !== $q['explanation']) {
                $explanation = $this->markdown->render($q['explanation'], $resolver, 3);
                $this->reportErrors($q['file'], $explanation->errors, $report, $code);
                $c->questions[$code]['explanationHtml'] = $explanation->html;
            }
        }
    }

    /** @param list<string> $errors */
    private function reportErrors(string $file, array $errors, ImportReport $report, ?string $context = null): void
    {
        foreach ($errors as $error) {
            $report->error($file, (null === $context ? '' : 'question « '.$context.' » : ').$error);
        }
    }

    private function resolver(PreparedContent $c, string $chapterCode): LinkResolver
    {
        $urls = $this->urls;
        $chapterDir = $c->chapters[$chapterCode]['dir'] ?? '';

        return new class($c, $urls, $chapterDir) implements LinkResolver {
            public function __construct(private PreparedContent $c, private UrlGeneratorInterface $urls, private string $chapterDir)
            {
            }

            public function resolve(InternalLink $link): ?string
            {
                return match ($link->scheme) {
                    'cours' => isset($this->c->courses[$link->target]) ? $this->urls->generate('course_show', ['code' => $link->target]) : null,
                    'exercice' => isset($this->c->exercises[$link->target]) ? $this->urls->generate('exercise_show', ['code' => $link->target]) : null,
                    'qcm' => 'savoir' === ($this->c->items[$link->target]['type'] ?? null) ? $this->urls->generate('quiz_intro', ['code' => $link->target]) : null,
                    'fichier' => $this->file($link->target),
                    default => null,
                };
            }

            private function file(string $target): ?string
            {
                $path = $this->chapterDir.'/fichiers/'.$target;
                if (str_contains($target, '..') || !isset($this->c->files[$path])) {
                    return null;
                }

                return $this->urls->generate('content_file', ['path' => $path]);
            }
        };
    }
}
