<?php

declare(strict_types=1);

namespace App\Content\Service;

use App\Content\Entity\Course;
use App\Content\Entity\Exercise;
use App\Content\Repository\CourseRepository;
use App\Content\Repository\ExerciseRepository;

/**
 * API de lecture du module Contenus.
 */
final readonly class ContentCatalog
{
    public function __construct(
        private CourseRepository $courses,
        private ExerciseRepository $exercises,
    ) {
    }

    public function course(string $code): ?Course
    {
        return $this->courses->findOneBy(['code' => $code, 'archived' => false]);
    }

    public function exercise(string $code): ?Exercise
    {
        return $this->exercises->findOneBy(['code' => $code, 'archived' => false]);
    }

    /** @return array<string, list<Course>> code du chapitre => cours */
    public function coursesByChapter(): array
    {
        $result = [];
        foreach ($this->courses->findBy(['archived' => false], ['position' => 'ASC', 'title' => 'ASC']) as $course) {
            $result[$course->getChapterCode()][] = $course;
        }

        return $result;
    }

    /** @return array<string, list<Exercise>> */
    public function exercisesByChapter(): array
    {
        $result = [];
        foreach ($this->exercises->findBy(['archived' => false], ['position' => 'ASC', 'title' => 'ASC']) as $exercise) {
            $result[$exercise->getChapterCode()][] = $exercise;
        }

        return $result;
    }

    /** @return array<string, list<Course>> code d'élément => cours qui le travaillent */
    public function coursesByItem(): array
    {
        $result = [];
        foreach ($this->courses->findBy(['archived' => false], ['position' => 'ASC']) as $course) {
            foreach ($course->getItemCodes() as $code) {
                $result[$code][] = $course;
            }
        }

        return $result;
    }

    /** @return list<Course> cours qui travaillent ce savoir */
    public function coursesForItem(string $itemCode): array
    {
        return array_values(array_filter(
            $this->courses->findBy(['archived' => false], ['position' => 'ASC']),
            static fn (Course $c) => \in_array($itemCode, $c->getItemCodes(), true),
        ));
    }

    /** @return list<Exercise> */
    public function exercisesForItem(string $itemCode): array
    {
        return array_values(array_filter(
            $this->exercises->findBy(['archived' => false], ['position' => 'ASC']),
            static fn (Exercise $e) => \in_array($itemCode, $e->getItemCodes(), true),
        ));
    }

    /**
     * Cours ordonnés comme dans le référentiel, pour les liens précédent / suivant.
     *
     * @param list<string> $chapterOrder
     *
     * @return array{0: ?Course, 1: ?Course}
     */
    public function neighbours(Course $course, array $chapterOrder): array
    {
        $byChapter = $this->coursesByChapter();
        $ordered = [];
        foreach ($chapterOrder as $chapter) {
            foreach ($byChapter[$chapter] ?? [] as $c) {
                $ordered[] = $c;
            }
        }
        $index = array_search($course, $ordered, true);
        if (false === $index) {
            return [null, null];
        }

        return [$ordered[$index - 1] ?? null, $ordered[$index + 1] ?? null];
    }
}
