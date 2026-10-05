<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Export;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvExporter
{
    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function download(string $resourceSlug, array $headings, array $rows, string $kind = 'export'): StreamedResponse
    {
        $filename = $this->buildFilename($resourceSlug, $kind);

        return response()->streamDownload(function () use ($headings, $rows): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }

            // UTF-8 BOM for spreadsheet compatibility.
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $headings);

            foreach ($rows as $row) {
                $safe = array_map(
                    fn (mixed $value): string => $this->escapeFormulaInjection($this->stringify($value)),
                    $row
                );
                fputcsv($stream, $safe);
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildFilename(string $resourceSlug, string $kind): string
    {
        $base = Str::slug($resourceSlug, '-');
        if ($base === '') {
            $base = 'resource';
        }

        $kind = Str::slug($kind, '-');
        if ($kind === '') {
            $kind = 'export';
        }

        return sprintf('%s-%s-%s.csv', $base, $kind, now()->format('Ymd-His'));
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value);

        return is_string($encoded) ? $encoded : '';
    }

    private function escapeFormulaInjection(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $first = $value[0];

        if (in_array($first, ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
    }
}
