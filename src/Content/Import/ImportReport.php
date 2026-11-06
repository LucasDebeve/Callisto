<?php

declare(strict_types=1);

namespace App\Content\Import;

/**
 * Rapport d'import affiché avant toute publication (EC2).
 */
final class ImportReport
{
    /** @var list<string> */
    private array $errors = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var array<string, int> */
    private array $stats = [];

    /** @var list<string> */
    private array $archived = [];

    public function error(string $file, string $message): void
    {
        $this->errors[] = '' === $file ? $message : $file.' : '.$message;
    }

    public function warning(string $file, string $message): void
    {
        $this->warnings[] = '' === $file ? $message : $file.' : '.$message;
    }

    public function count(string $key, int $n = 1): void
    {
        $this->stats[$key] = ($this->stats[$key] ?? 0) + $n;
    }

    public function willArchive(string $what): void
    {
        $this->archived[] = $what;
    }

    public function isValid(): bool
    {
        return [] === $this->errors;
    }

    /** @return list<string> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @return list<string> */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /** @return array<string, int> */
    public function getStats(): array
    {
        return $this->stats;
    }

    /** @return list<string> */
    public function getArchived(): array
    {
        return $this->archived;
    }
}
