<?php

declare(strict_types=1);

namespace App\Content\Controller;

use App\Content\Entity\ContentFile;
use App\Content\Repository\ContentFileRepository;
use App\Content\Service\ContentCatalog;
use App\Referential\Entity\Chapter;
use App\Referential\Service\ReferentialCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Cours et exercices : pages publiques, lisibles sans connexion (décision du cadrage).
 */
final class ContentController extends AbstractController
{
    public function __construct(
        private readonly ContentCatalog $content,
        private readonly ReferentialCatalog $referential,
    ) {
    }

    #[Route('/cours', name: 'course_index', methods: ['GET'])]
    public function index(): Response
    {
        $levels = $this->referential->levels();
        $themes = [];
        foreach ($levels as $level) {
            $themes[$level->getCode()] = $this->referential->themesOf($level->getCode());
        }

        return $this->render('content/index.html.twig', [
            'levels' => $levels,
            'themesByLevel' => $themes,
            'courses' => $this->content->coursesByChapter(),
            'exercises' => $this->content->exercisesByChapter(),
        ]);
    }

    #[Route('/cours/{code}', name: 'course_show', requirements: ['code' => '[a-z0-9._-]+'], methods: ['GET'])]
    public function course(string $code): Response
    {
        $course = $this->content->course($code) ?? throw $this->createNotFoundException();
        $chapter = $this->referential->chapter($course->getChapterCode());
        $chapterOrder = $this->chapterOrder($chapter);
        [$previous, $next] = $this->content->neighbours($course, $chapterOrder);

        return $this->render('content/course.html.twig', [
            'course' => $course,
            'chapter' => $chapter,
            'previous' => $previous,
            'next' => $next,
            'items' => array_values(array_filter(array_map(fn (string $c) => $this->referential->item($c), $course->getItemCodes()))),
            'exercises' => $this->content->exercisesByChapter()[$course->getChapterCode()] ?? [],
            'chapterCourses' => $this->content->coursesByChapter()[$course->getChapterCode()] ?? [],
        ]);
    }

    #[Route('/exercices/{code}', name: 'exercise_show', requirements: ['code' => '[a-z0-9._-]+'], methods: ['GET'])]
    public function exercise(string $code): Response
    {
        $exercise = $this->content->exercise($code) ?? throw $this->createNotFoundException();

        return $this->render('content/exercise.html.twig', [
            'exercise' => $exercise,
            'chapter' => $this->referential->chapter($exercise->getChapterCode()),
            'courses' => $this->content->coursesByChapter()[$exercise->getChapterCode()] ?? [],
        ]);
    }

    /**
     * Pièces jointes : les images des cours sont publiques, les autres fichiers exigent une connexion.
     */
    #[Route('/fichiers/{path}', name: 'content_file', requirements: ['path' => '[A-Za-z0-9._/-]+'], methods: ['GET'])]
    #[Cache(maxage: 3600, public: false)]
    public function attachment(string $path, ContentFileRepository $files, string $filesDir): Response
    {
        $file = $files->findOneBy(['path' => $path, 'archived' => false]);
        if (!$file instanceof ContentFile) {
            throw $this->createNotFoundException();
        }
        if (!$file->isPublic()) {
            $this->denyAccessUnlessGranted('IS_AUTHENTICATED');
        }
        $absolute = $filesDir.'/'.$file->getStoredName();
        if (!is_file($absolute)) {
            throw $this->createNotFoundException();
        }
        $response = new BinaryFileResponse($absolute);
        $response->headers->set('Content-Type', $file->getMimeType());
        // Images affichées dans la page ; autres fichiers toujours téléchargés. Un SVG ouvert directement
        // est isolé par une CSP « sandbox » (aucun script exécuté).
        $response->setContentDisposition($file->isPublic() ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->getFileName());
        $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");

        return $response;
    }

    /** @return list<string> */
    private function chapterOrder(?Chapter $chapter): array
    {
        if (null === $chapter) {
            return [];
        }
        $order = [];
        foreach ($this->referential->themesOf($chapter->getTheme()->getLevel()->getCode()) as $theme) {
            foreach ($theme->getChapters() as $c) {
                $order[] = $c->getCode();
            }
        }

        return $order;
    }
}
