<?php

declare(strict_types=1);

namespace App\Content\Import;

use App\Content\Entity\ContentFile;
use App\Content\Entity\ContentImport;
use App\Content\Entity\Course;
use App\Content\Entity\Exercise;
use App\Content\Repository\ContentFileRepository;
use App\Content\Repository\CourseRepository;
use App\Content\Repository\ExerciseRepository;
use App\Quiz\Domain\QuestionType;
use App\Quiz\Entity\Question;
use App\Quiz\Repository\QuestionRepository;
use App\Referential\Domain\ItemType;
use App\Referential\Entity\Chapter;
use App\Referential\Entity\Item;
use App\Referential\Entity\Level;
use App\Referential\Entity\Theme;
use App\Referential\Repository\ChapterRepository;
use App\Referential\Repository\ItemRepository;
use App\Referential\Repository\LevelRepository;
use App\Referential\Repository\ThemeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Publication d'un contenu validé, dans une transaction unique. Rapprochement par identifiant stable :
 * un élément qui disparaît est archivé, jamais supprimé, et aucune validation n'est touchée (EC5).
 *
 * Note d'architecture : l'import est le seul composant autorisé à écrire dans les tables
 * du référentiel, des contenus et de la banque de questions.
 */
final readonly class ContentPublisher
{
    public function __construct(
        private EntityManagerInterface $em,
        private LevelRepository $levels,
        private ThemeRepository $themes,
        private ChapterRepository $chapters,
        private ItemRepository $items,
        private CourseRepository $courses,
        private ExerciseRepository $exercises,
        private QuestionRepository $questions,
        private ContentFileRepository $files,
        private ClockInterface $clock,
        private string $filesDir,
    ) {
    }

    /** Liste ce qui serait archivé, pour le rapport. */
    public function previewArchives(PreparedContent $c, ImportReport $report): void
    {
        foreach ([
            'thème' => [$this->themes, $c->themes],
            'chapitre' => [$this->chapters, $c->chapters],
            'élément' => [$this->items, $c->items],
            'cours' => [$this->courses, $c->courses],
            'exercice' => [$this->exercises, $c->exercises],
            'question' => [$this->questions, $c->questions],
        ] as $label => [$repository, $incoming]) {
            foreach ($repository->findBy(['archived' => false]) as $entity) {
                if (!isset($incoming[$entity->getCode()])) {
                    $report->willArchive(\sprintf('%s « %s »', $label, $entity->getCode()));
                }
            }
        }
    }

    public function publish(PreparedContent $c, ImportReport $report, ?string $actor): ContentImport
    {
        if (!$report->isValid()) {
            throw new \LogicException('Un contenu invalide ne peut pas être publié.');
        }
        $storedFiles = $this->storeFiles($c);

        return $this->em->wrapInTransaction(function () use ($c, $report, $actor, $storedFiles): ContentImport {
            $levels = $this->syncLevels($c);
            $themes = $this->syncThemes($c, $levels);
            $chapters = $this->syncChapters($c, $themes);
            $this->syncItems($c, $chapters);
            $this->syncCourses($c);
            $this->syncExercises($c);
            $this->syncQuestions($c);
            $this->syncFiles($storedFiles);

            $import = new ContentImport($this->clock->now(), $c->revision, $report->getStats(), $report->getWarnings(), $actor);
            $this->em->persist($import);
            $this->em->flush();

            return $import;
        });
    }

    /** @return array<string, Level> */
    private function syncLevels(PreparedContent $c): array
    {
        $result = [];
        foreach ($c->levels as $code => $data) {
            $level = $this->levels->find($code) ?? new Level($code, $data['title']);
            $level->update($data['title'], $data['position']);
            $this->em->persist($level);
            $result[$code] = $level;
        }

        return $result;
    }

    /**
     * @param array<string, Level> $levels
     *
     * @return array<string, Theme>
     */
    private function syncThemes(PreparedContent $c, array $levels): array
    {
        $existing = $this->indexByCode($this->themes->findAll());
        $result = [];
        foreach ($c->themes as $code => $data) {
            $theme = $existing[$code] ?? new Theme($code, $levels[$data['level']], $data['title']);
            $theme->update($levels[$data['level']], $data['title'], $data['description'], $data['position']);
            $this->em->persist($theme);
            $result[$code] = $theme;
        }
        $this->archiveMissing($existing, $c->themes);

        return $result;
    }

    /**
     * @param array<string, Theme> $themes
     *
     * @return array<string, Chapter>
     */
    private function syncChapters(PreparedContent $c, array $themes): array
    {
        $existing = $this->indexByCode($this->chapters->findAll());
        $result = [];
        foreach ($c->chapters as $code => $data) {
            $chapter = $existing[$code] ?? new Chapter($code, $themes[$data['theme']], $data['title']);
            $chapter->update($themes[$data['theme']], $data['title'], $data['position']);
            $this->em->persist($chapter);
            $result[$code] = $chapter;
        }
        $this->archiveMissing($existing, $c->chapters);

        return $result;
    }

    /**
     * @param array<string, Chapter> $chapters
     */
    private function syncItems(PreparedContent $c, array $chapters): void
    {
        $existing = $this->indexByCode($this->items->findAll());
        $result = [];
        foreach ($c->items as $code => $data) {
            $type = ItemType::from($data['type']);
            $item = $existing[$code] ?? new Item($code, $chapters[$data['chapter']], $type, $data['title']);
            $item->update(
                $chapters[$data['chapter']],
                $type,
                $data['title'],
                $data['description'],
                $data['threshold'],
                $data['questionsPerAttempt'],
                $data['layout']['x'] ?? null,
                $data['layout']['y'] ?? null,
                $data['position'],
            );
            $this->em->persist($item);
            $result[$code] = $item;
        }
        foreach ($c->items as $code => $data) {
            $result[$code]->replacePrerequisites(array_map(static fn (string $p) => $result[$p], $data['prerequisites']));
        }
        $this->archiveMissing($existing, $c->items);
    }

    private function syncCourses(PreparedContent $c): void
    {
        $existing = $this->indexByCode($this->courses->findAll());
        foreach ($c->courses as $code => $data) {
            $course = $existing[$code] ?? new Course($code, $data['chapter'], $data['title']);
            $course->update($data['chapter'], $data['title'], $data['html'], $data['toc'], $data['items'], $data['position']);
            $this->em->persist($course);
        }
        $this->archiveMissing($existing, $c->courses);
    }

    private function syncExercises(PreparedContent $c): void
    {
        $existing = $this->indexByCode($this->exercises->findAll());
        foreach ($c->exercises as $code => $data) {
            $exercise = $existing[$code] ?? new Exercise($code, $data['chapter'], $data['title']);
            $exercise->update($data['chapter'], $data['title'], $data['statementHtml'], $data['solutionHtml'], $data['published'], $data['items'], $data['position']);
            $this->em->persist($exercise);
        }
        $this->archiveMissing($existing, $c->exercises);
    }

    private function syncQuestions(PreparedContent $c): void
    {
        $existing = $this->indexByCode($this->questions->findAll());
        foreach ($c->questions as $code => $data) {
            $type = QuestionType::from($data['type']);
            $question = $existing[$code] ?? new Question($code, $data['item'], $type);
            $question->update(
                $data['item'],
                $type,
                $data['promptHtml'],
                $data['codeHtml'],
                QuestionType::Short === $type ? [] : $data['choicesHtml'],
                $data['accepted'],
                $data['explanationHtml'],
            );
            $this->em->persist($question);
        }
        $this->archiveMissing($existing, $c->questions);
    }

    /**
     * Copie les fichiers dans le stockage, nommés par empreinte (idempotent, hors transaction).
     *
     * @return array<string, array{stored: string, mime: string, size: int}>
     */
    private function storeFiles(PreparedContent $c): array
    {
        $fs = new Filesystem();
        $fs->mkdir($this->filesDir, 0o750);
        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        $stored = [];
        foreach ($c->files as $relative => $file) {
            $hash = (string) hash_file('sha256', $file['absolute']);
            $extension = strtolower(pathinfo($relative, \PATHINFO_EXTENSION));
            $name = $hash.'.'.$extension;
            if (!is_file($this->filesDir.'/'.$name)) {
                $fs->copy($file['absolute'], $this->filesDir.'/'.$name);
            }
            $mime = 'svg' === $extension ? 'image/svg+xml' : (string) $finfo->file($file['absolute']);
            $stored[$relative] = ['stored' => $name, 'mime' => '' === $mime ? 'application/octet-stream' : $mime, 'size' => (int) filesize($file['absolute'])];
        }

        return $stored;
    }

    /**
     * @param array<string, array{stored: string, mime: string, size: int}> $stored
     */
    private function syncFiles(array $stored): void
    {
        $existing = [];
        foreach ($this->files->findAll() as $file) {
            $existing[$file->getPath()] = $file;
        }
        foreach ($stored as $path => $data) {
            $file = $existing[$path] ?? new ContentFile($path, $data['stored'], $data['mime'], $data['size']);
            $file->update($data['stored'], $data['mime'], $data['size']);
            $this->em->persist($file);
        }
        foreach ($existing as $path => $file) {
            if (!isset($stored[$path])) {
                $file->archive();
            }
        }
    }

    /**
     * @template T of object
     *
     * @param list<T> $entities
     *
     * @return array<string, T>
     */
    private function indexByCode(array $entities): array
    {
        $result = [];
        foreach ($entities as $entity) {
            \assert(method_exists($entity, 'getCode'));
            $result[(string) $entity->getCode()] = $entity;
        }

        return $result;
    }

    /**
     * @param array<string, object> $existing
     * @param array<string, mixed>  $incoming
     */
    private function archiveMissing(array $existing, array $incoming): void
    {
        foreach ($existing as $code => $entity) {
            if (!isset($incoming[$code]) && method_exists($entity, 'archive')) {
                $entity->archive();
            }
        }
    }
}
