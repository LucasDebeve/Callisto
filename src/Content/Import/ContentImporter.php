<?php

declare(strict_types=1);

namespace App\Content\Import;

use App\Administration\AuditLogger;
use App\Content\Entity\ContentImport;

/**
 * Façade de l'import : analyse (lecture, schémas, cohérence, rendu, rapport) puis publication.
 */
final readonly class ContentImporter
{
    public function __construct(
        private ContentLoader $loader,
        private ContentValidator $validator,
        private ContentPublisher $publisher,
        private AuditLogger $audit,
        private string $contentDir,
    ) {
    }

    public function defaultDirectory(): string
    {
        return $this->contentDir;
    }

    /**
     * @return array{0: ImportReport, 1: PreparedContent}
     */
    public function analyze(?string $directory = null, bool $compareWithDatabase = true): array
    {
        $report = new ImportReport();
        $content = $this->loader->load($directory ?? $this->contentDir, $report);
        $this->validator->validate($content, $report);
        if ($compareWithDatabase) {
            $this->publisher->previewArchives($content, $report);
        }

        return [$report, $content];
    }

    public function publish(PreparedContent $content, ImportReport $report, ?string $actor): ContentImport
    {
        $import = $this->publisher->publish($content, $report, $actor);
        $this->audit->log(AuditLogger::CONTENT_IMPORTED, $actor, $content->revision, $report->getStats());

        return $import;
    }
}
