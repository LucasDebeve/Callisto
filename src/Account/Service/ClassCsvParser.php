<?php

declare(strict_types=1);

namespace App\Account\Service;

/**
 * Lecture d'une liste de classe collée depuis un tableur (EP1) : identifiant, prénom et initiale, classe.
 * Séparateur « ; », « , » ou tabulation, ligne d'en-tête facultative. Aucun fichier n'est déposé (S15).
 */
final class ClassCsvParser
{
    public const MAX_ROWS = 60;

    /**
     * @return array{rows: list<array{username: string, displayName: string, className: string, line: int}>, errors: list<string>}
     */
    public function parse(string $content): array
    {
        $content = str_replace(["\r\n", "\r"], "\n", trim($content));
        $content = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = '' === $content ? [] : explode("\n", $content);
        $rows = [];
        $errors = [];
        $seen = [];

        if ([] === $lines) {
            return ['rows' => [], 'errors' => ['La liste est vide.']];
        }

        $delimiter = $this->detectDelimiter($lines[0]);
        foreach ($lines as $index => $line) {
            $number = $index + 1;
            if ('' === trim($line)) {
                continue;
            }
            $cells = array_map(static fn (?string $c) => trim((string) $c), str_getcsv($line, $delimiter, '"', ''));
            if (1 === $number && $this->isHeader($cells)) {
                continue;
            }
            if (\count($cells) < 3) {
                $errors[] = \sprintf('Ligne %d : trois colonnes attendues (identifiant, prénom et initiale, classe).', $number);
                continue;
            }
            [$username, $displayName, $className] = $cells;
            $username = mb_strtolower($username);
            if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,62}$/', $username)) {
                $errors[] = \sprintf('Ligne %d : identifiant « %s » invalide (lettres minuscules sans accent, chiffres, point, tiret).', $number, $username);
                continue;
            }
            if ('' === $displayName || mb_strlen($displayName) > 80) {
                $errors[] = \sprintf('Ligne %d : prénom et initiale manquants ou trop longs.', $number);
                continue;
            }
            if (preg_match('/\S+\s+\S{2,}/u', $displayName) && !preg_match('/\S+\s+\S\.?$/u', $displayName)) {
                $errors[] = \sprintf('Ligne %d : seul le prénom et l\'initiale du nom sont attendus (R1), par exemple « Léa M. ».', $number);
                continue;
            }
            if ('' === $className || mb_strlen($className) > 40) {
                $errors[] = \sprintf('Ligne %d : classe manquante.', $number);
                continue;
            }
            if (isset($seen[$username])) {
                $errors[] = \sprintf('Ligne %d : identifiant « %s » en double (déjà ligne %d).', $number, $username, $seen[$username]);
                continue;
            }
            $seen[$username] = $number;
            $rows[] = ['username' => $username, 'displayName' => $displayName, 'className' => $className, 'line' => $number];
        }

        if (\count($rows) > self::MAX_ROWS) {
            $errors[] = \sprintf('Trop de lignes (%d) : %d au maximum par import.', \count($rows), self::MAX_ROWS);
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [';' => substr_count($line, ';'), "\t" => substr_count($line, "\t"), ',' => substr_count($line, ',')];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /** @param list<string> $cells */
    private function isHeader(array $cells): bool
    {
        return isset($cells[0]) && \in_array(mb_strtolower($cells[0]), ['identifiant', 'login', 'id', 'username'], true);
    }
}
