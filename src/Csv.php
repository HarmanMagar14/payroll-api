<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Minimal CSV reader. Files live in /data and are read once per Lambda
 * container (cached in a static property between warm invocations).
 */
final class Csv
{
    /** @var array<string, list<array<string, string|null>>> */
    private static array $cache = [];

    /** @return list<array<string, string|null>> */
    public static function read(string $table): array
    {
        if (isset(self::$cache[$table])) {
            return self::$cache[$table];
        }

        $path = dirname(__DIR__) . "/data/{$table}.csv";
        if (!is_readable($path)) {
            throw new RuntimeException("CSV file not found: data/{$table}.csv");
        }

        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle, 0, ',', '"', '\\');
        if ($headers === false) {
            throw new RuntimeException("CSV file is empty: data/{$table}.csv");
        }
        // Strip a UTF-8 BOM and stray whitespace from the header row
        $headers = array_map(fn ($h) => trim((string) $h), $headers);
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);

        $rows = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($row === [null] || count($row) !== count($headers)) {
                continue; // skip blank or malformed lines
            }
            $record = array_combine($headers, $row);
            // The dataset writes missing values as the literal text "null"
            foreach ($record as $key => $value) {
                if ($value === '' || strtolower($value) === 'null') {
                    $record[$key] = null;
                }
            }
            $rows[] = $record;
        }
        fclose($handle);

        return self::$cache[$table] = $rows;
    }

    public static function exists(string $table): bool
    {
        return is_readable(dirname(__DIR__) . "/data/{$table}.csv");
    }

    /**
     * Rows keyed by their id column. Returns [] for optional lookup tables
     * (departments, positions) when their CSV is not in /data.
     *
     * @return array<int, array<string, string|null>>
     */
    public static function indexById(string $table, bool $optional = false): array
    {
        if ($optional && !self::exists($table)) {
            return [];
        }

        $indexed = [];
        foreach (self::read($table) as $row) {
            $indexed[(int) $row['id']] = $row;
        }
        return $indexed;
    }
}
