<?php

declare(strict_types=1);

use SeviaFlow\Core\Export\CsvExporter;

it('streams utf8 csv with headings and escaped formula-like values', function (): void {
    $response = (new CsvExporter)->download('orders', ['ID', 'Name', 'Email'], [
        [1, 'Alice', 'alice@example.com'],
        [2, '=HYPERLINK("http://evil.test")', '+sum'],
        [3, '-cmd', '@danger'],
    ]);

    ob_start();
    $response->sendContent();
    $content = (string) ob_get_clean();

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($content)->toStartWith("\xEF\xBB\xBF")
        ->and($content)->toContain('ID,Name,Email')
        ->and($content)->toContain("'=HYPERLINK")
        ->and($content)->toContain("'+sum")
        ->and($content)->toContain("'-cmd")
        ->and($content)->toContain("'@danger");
});
