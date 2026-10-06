<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Import;

final class CsvImportPreviewer
{
    /**
     * @param  array<int, string>  $allowedFields
     * @param  array<int, string>  $requiredFields
     * @return array{
     *   headers: array<int, string>,
     *   rows: array<int, array{index:int, values:array<string, string>, errors:array<int, string>}>,
     *   errors: array<int, string>
     * }
     */
    public function preview(string $path, array $allowedFields, array $requiredFields, int $rowLimit = 20): array
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            return [
                'headers' => [],
                'rows' => [],
                'errors' => ['Could not read CSV file.'],
            ];
        }

        $headers = fgetcsv($stream);
        if ($headers === false) {
            fclose($stream);

            return [
                'headers' => [],
                'rows' => [],
                'errors' => ['CSV headers are required.'],
            ];
        }

        $normalizedHeaders = array_values(array_map(
            static fn (mixed $value): string => $value === null ? '' : trim((string) $value),
            $headers
        ));

        $errors = [];
        $allowedLookup = array_fill_keys($allowedFields, true);

        $unknownHeaders = array_values(array_filter(
            $normalizedHeaders,
            static fn (string $header): bool => $header !== '' && ! isset($allowedLookup[$header])
        ));
        if ($unknownHeaders !== []) {
            $errors[] = sprintf('Unknown fields are not allowed: %s.', implode(', ', $unknownHeaders));
        }

        $missingRequired = [];
        foreach ($requiredFields as $requiredField) {
            if (! in_array($requiredField, $normalizedHeaders, true)) {
                $missingRequired[] = $requiredField;
            }
        }
        if ($missingRequired !== []) {
            $errors[] = sprintf('Missing required fields: %s.', implode(', ', $missingRequired));
        }

        $rows = [];
        $rowIndex = 1;
        $rowLimit = max(1, $rowLimit);

        while (($line = fgetcsv($stream)) !== false && count($rows) < $rowLimit) {
            $mapped = [];
            foreach ($normalizedHeaders as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $value = $line[$index] ?? '';
                $mapped[$header] = trim((string) $value);
            }

            $rowErrors = [];
            foreach ($requiredFields as $requiredField) {
                if (! array_key_exists($requiredField, $mapped)) {
                    continue;
                }

                if ($mapped[$requiredField] === '') {
                    $rowErrors[] = sprintf('Field [%s] is required.', $requiredField);
                }
            }

            $rows[] = [
                'index' => $rowIndex,
                'values' => $mapped,
                'errors' => $rowErrors,
            ];
            $rowIndex++;
        }

        fclose($stream);

        return [
            'headers' => $normalizedHeaders,
            'rows' => $rows,
            'errors' => $errors,
        ];
    }
}
