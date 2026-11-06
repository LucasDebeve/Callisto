<?php

declare(strict_types=1);

namespace App\Content\Import;

use Symfony\Component\Config\Definition\Exception\Exception as ConfigException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Lit l'arborescence du dépôt de contenus et la valide contre les schémas. Aucune écriture.
 *
 *   niveaux.yaml
 *   <niveau>/<theme>/theme.yaml
 *   <niveau>/<theme>/<chapitre>/chapitre.yaml
 *   <niveau>/<theme>/<chapitre>/cours/*.md
 *   <niveau>/<theme>/<chapitre>/exercices/*.md (+ *.corrige.md)
 *   <niveau>/<theme>/<chapitre>/qcm/*.yaml
 *   <niveau>/<theme>/<chapitre>/fichiers/**
 */
final class ContentLoader
{
    public const MAX_FILE_SIZE = 20 * 1024 * 1024;
    private const ALLOWED_FILE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'pdf', 'py', 'txt', 'csv', 'zip', 'odt', 'ods', 'odp', 'md', 'json', 'html', 'css', 'js', 'sql', 'db'];

    public function __construct(private readonly ContentSchema $schema = new ContentSchema())
    {
    }

    public function load(string $root, ImportReport $report): PreparedContent
    {
        $content = new PreparedContent();
        $root = rtrim($root, '/');
        if (!is_dir($root)) {
            $report->error('', \sprintf('Dossier de contenus introuvable : %s', $root));

            return $content;
        }
        $content->revision = $this->gitRevision($root);

        $levels = $this->yaml($root, 'niveaux.yaml', 'niveaux', $report);
        foreach ($levels['niveaux'] ?? [] as $position => $level) {
            if (isset($content->levels[$level['code']])) {
                $report->error('niveaux.yaml', \sprintf('niveau « %s » en double.', $level['code']));
            }
            $content->levels[$level['code']] = ['title' => $level['titre'], 'position' => (int) $position];
        }

        foreach (array_keys($content->levels) as $levelCode) {
            $levelDir = $root.'/'.$levelCode;
            if (!is_dir($levelDir)) {
                $report->warning('', \sprintf('aucun dossier pour le niveau « %s ».', $levelCode));
                continue;
            }
            foreach ($this->subdirectories($levelDir) as $themeDir) {
                $this->loadTheme($root, $levelCode, $themeDir, $content, $report);
            }
        }

        return $content;
    }

    private function loadTheme(string $root, string $levelCode, string $themeDir, PreparedContent $content, ImportReport $report): void
    {
        $relative = $this->relative($root, $themeDir);
        $theme = $this->yaml($root, $relative.'/theme.yaml', 'theme', $report);
        if (null === $theme) {
            return;
        }
        $code = $theme['code'];
        if (isset($content->themes[$code])) {
            $report->error($relative.'/theme.yaml', \sprintf('thème « %s » en double.', $code));

            return;
        }
        $content->themes[$code] = [
            'level' => $levelCode,
            'title' => $theme['titre'],
            'description' => $theme['description'],
            'position' => $theme['ordre'],
            'file' => $relative.'/theme.yaml',
        ];

        foreach ($this->subdirectories($themeDir) as $chapterDir) {
            $this->loadChapter($root, $code, $chapterDir, $content, $report);
        }
    }

    private function loadChapter(string $root, string $themeCode, string $chapterDir, PreparedContent $content, ImportReport $report): void
    {
        $relative = $this->relative($root, $chapterDir);
        $file = $relative.'/chapitre.yaml';
        $chapter = $this->yaml($root, $file, 'chapitre', $report);
        if (null === $chapter) {
            return;
        }
        $code = $chapter['code'];
        if (isset($content->chapters[$code])) {
            $report->error($file, \sprintf('chapitre « %s » en double.', $code));

            return;
        }
        $content->chapters[$code] = ['theme' => $themeCode, 'title' => $chapter['titre'], 'position' => $chapter['ordre'], 'file' => $file, 'dir' => $relative];

        foreach ($chapter['elements'] as $position => $item) {
            if (isset($content->items[$item['code']])) {
                $report->error($file, \sprintf('élément « %s » en double (déjà dans %s).', $item['code'], $content->items[$item['code']]['file']));
                continue;
            }
            $content->items[$item['code']] = [
                'chapter' => $code,
                'type' => $item['type'],
                'title' => $item['titre'],
                'description' => $item['description'],
                'threshold' => $item['seuil'],
                'questionsPerAttempt' => $item['questions_par_tentative'],
                'prerequisites' => array_values(array_map('strval', $item['prerequis'])),
                'layout' => isset($item['position']) ? ['x' => (float) $item['position']['x'], 'y' => (float) $item['position']['y']] : null,
                'position' => (int) $position,
                'file' => $file,
            ];
        }

        $this->loadCourses($root, $code, $chapterDir, $content, $report);
        $this->loadExercises($root, $code, $chapterDir, $content, $report);
        $this->loadQuizzes($root, $chapterDir, $content, $report);
        $this->loadFiles($root, $code, $chapterDir, $content, $report);
    }

    private function loadCourses(string $root, string $chapterCode, string $chapterDir, PreparedContent $content, ImportReport $report): void
    {
        foreach ($this->files($chapterDir.'/cours', '*.md') as $path) {
            $file = $this->relative($root, $path);
            [$meta, $body] = $this->frontMatter($path, $file, 'cours', $report);
            if (null === $meta) {
                continue;
            }
            if (isset($content->courses[$meta['code']])) {
                $report->error($file, \sprintf('cours « %s » en double.', $meta['code']));
                continue;
            }
            $content->courses[$meta['code']] = [
                'chapter' => $chapterCode,
                'title' => $meta['titre'],
                'position' => $meta['ordre'],
                'items' => array_values(array_map('strval', $meta['savoirs'])),
                'markdown' => $body,
                'html' => '',
                'toc' => [],
                'file' => $file,
            ];
        }
    }

    private function loadExercises(string $root, string $chapterCode, string $chapterDir, PreparedContent $content, ImportReport $report): void
    {
        foreach ($this->files($chapterDir.'/exercices', '*.md') as $path) {
            if (str_ends_with($path, '.corrige.md')) {
                if (!is_file(substr($path, 0, -\strlen('.corrige.md')).'.md')) {
                    $report->error($this->relative($root, $path), 'corrigé sans énoncé correspondant.');
                }
                continue;
            }
            $file = $this->relative($root, $path);
            [$meta, $body] = $this->frontMatter($path, $file, 'exercice', $report);
            if (null === $meta) {
                continue;
            }
            if (isset($content->exercises[$meta['code']])) {
                $report->error($file, \sprintf('exercice « %s » en double.', $meta['code']));
                continue;
            }
            $solutionPath = substr($path, 0, -3).'.corrige.md';
            $solution = is_file($solutionPath) ? (string) file_get_contents($solutionPath) : null;
            if ($meta['corrige_publie'] && null === $solution) {
                $report->warning($file, 'corrige_publie vaut true mais aucun fichier .corrige.md n\'existe.');
            }
            $content->exercises[$meta['code']] = [
                'chapter' => $chapterCode,
                'title' => $meta['titre'],
                'position' => $meta['ordre'],
                'items' => array_values(array_map('strval', $meta['elements'])),
                'published' => $meta['corrige_publie'],
                'statement' => $body,
                'solution' => $solution,
                'statementHtml' => '',
                'solutionHtml' => null,
                'file' => $file,
            ];
        }
    }

    private function loadQuizzes(string $root, string $chapterDir, PreparedContent $content, ImportReport $report): void
    {
        foreach ($this->files($chapterDir.'/qcm', '*.yaml') as $path) {
            $file = $this->relative($root, $path);
            $quiz = $this->yaml($root, $file, 'qcm', $report);
            if (null === $quiz) {
                continue;
            }
            foreach ($quiz['questions'] as $question) {
                if (isset($content->questions[$question['code']])) {
                    $report->error($file, \sprintf('question « %s » en double (déjà dans %s).', $question['code'], $content->questions[$question['code']]['file']));
                    continue;
                }
                $content->questions[$question['code']] = [
                    'item' => $quiz['savoir'],
                    'type' => $question['type'],
                    'prompt' => (string) $question['enonce'],
                    'code' => null === $question['code_source'] ? null : (string) $question['code_source'],
                    'language' => (string) $question['langage'],
                    'choices' => array_values(array_map(static fn (array $c) => ['texte' => (string) $c['texte'], 'juste' => (bool) $c['juste']], $question['choix'])),
                    'accepted' => array_values(array_map('strval', $question['reponses_acceptees'])),
                    'explanation' => null === $question['explication'] ? null : (string) $question['explication'],
                    'promptHtml' => '',
                    'codeHtml' => null,
                    'choicesHtml' => [],
                    'explanationHtml' => null,
                    'file' => $file,
                ];
            }
        }
    }

    private function loadFiles(string $root, string $chapterCode, string $chapterDir, PreparedContent $content, ImportReport $report): void
    {
        if (!is_dir($chapterDir.'/fichiers')) {
            return;
        }
        foreach ((new Finder())->files()->in($chapterDir.'/fichiers')->ignoreDotFiles(true)->sortByName() as $file) {
            $relative = $this->relative($root, $file->getPathname());
            $extension = strtolower($file->getExtension());
            if (!\in_array($extension, self::ALLOWED_FILE_EXTENSIONS, true)) {
                $report->error($relative, \sprintf('extension « %s » non autorisée.', $extension));
                continue;
            }
            if ($file->getSize() > self::MAX_FILE_SIZE) {
                $report->error($relative, 'fichier trop volumineux (20 Mo au maximum).');
                continue;
            }
            $content->files[$relative] = ['absolute' => $file->getPathname(), 'chapter' => $chapterCode];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function yaml(string $root, string $relative, string $kind, ImportReport $report): ?array
    {
        $path = $root.'/'.$relative;
        if (!is_file($path)) {
            $report->error($relative, 'fichier manquant.');

            return null;
        }
        try {
            $data = Yaml::parseFile($path);
        } catch (ParseException $e) {
            $report->error($relative, 'YAML invalide : '.$e->getMessage());

            return null;
        }

        return $this->validate($data, $relative, $kind, $report);
    }

    /**
     * @return array{0: array<string, mixed>|null, 1: string}
     */
    private function frontMatter(string $path, string $relative, string $kind, ImportReport $report): array
    {
        $raw = str_replace("\r\n", "\n", (string) file_get_contents($path));
        if (!preg_match('/\A---\n(.*?)\n---\n?(.*)\z/s', $raw, $m)) {
            $report->error($relative, 'en-tête YAML (entre deux lignes « --- ») manquant.');

            return [null, ''];
        }
        try {
            $data = Yaml::parse($m[1]);
        } catch (ParseException $e) {
            $report->error($relative, 'en-tête YAML invalide : '.$e->getMessage());

            return [null, ''];
        }

        return [$this->validate($data, $relative, $kind, $report), $m[2]];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function validate(mixed $data, string $relative, string $kind, ImportReport $report): ?array
    {
        if (!\is_array($data)) {
            $report->error($relative, 'le contenu doit être un dictionnaire YAML.');

            return null;
        }
        try {
            return $this->schema->process($kind, $data);
        } catch (ConfigException $e) {
            $report->error($relative, 'schéma non respecté : '.$e->getMessage());

            return null;
        }
    }

    /** @return list<string> */
    private function subdirectories(string $dir): array
    {
        $dirs = [];
        foreach ((new Finder())->directories()->in($dir)->depth(0)->ignoreDotFiles(true)->sortByName() as $sub) {
            if (!\in_array($sub->getFilename(), ['cours', 'exercices', 'qcm', 'fichiers'], true)) {
                $dirs[] = $sub->getPathname();
            }
        }

        return $dirs;
    }

    /** @return list<string> */
    private function files(string $dir, string $pattern): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $files = [];
        foreach ((new Finder())->files()->in($dir)->depth(0)->name($pattern)->sortByName() as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }

    private function relative(string $root, string $path): string
    {
        return ltrim(substr($path, \strlen($root)), '/');
    }

    private function gitRevision(string $root): ?string
    {
        $head = $root.'/.git/HEAD';
        if (!is_file($head)) {
            return null;
        }
        $ref = trim((string) file_get_contents($head));
        if (str_starts_with($ref, 'ref: ')) {
            $refFile = $root.'/.git/'.substr($ref, 5);

            return is_file($refFile) ? substr(trim((string) file_get_contents($refFile)), 0, 12) : null;
        }

        return substr($ref, 0, 12);
    }
}
