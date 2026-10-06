<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use SeviaFlow\Core\Forms\FormStateInitializer;
use SeviaFlow\Core\Resources\Fields\RichTextField;
use SeviaFlow\Core\Resources\Fields\Support\RichTextSanitizer;

it('normalizes rich text to the bounded sanitized HTML contract', function (): void {
    $field = RichTextField::make('description');
    $value = $field->prepareForValidation(
        '<h2 onclick="alert(1)">Zażółć</h2><p><strong>Opis</strong> <em>PL</em></p>'
        .'<ul><li>Pierwszy</li></ul><a href="https://example.test" target="_blank">Link</a>'
        .'<a href="javascript:alert(1)">Niebezpieczny</a><script>alert(1)</script>'
    );

    expect($value)->toContain('<h2>Zażółć</h2>')
        ->and($value)->toContain('<strong>Opis</strong>')
        ->and($value)->toContain('href="https://example.test"')
        ->and($value)->not->toContain('onclick')
        ->and($value)->not->toContain('target=')
        ->and($value)->not->toContain('javascript:')
        ->and($value)->not->toContain('<script');
});

it('sanitizes descendants before unwrapping nested unsupported containers', function (): void {
    $sanitizer = new RichTextSanitizer;
    $input = '<div data-wrapper="unsafe"><span onclick="alert(1)"><section data-section="unsafe">'
        .'<p onmouseover="alert(2)">Widoczny</p>'
        .'<a href="java&#x73;cript:alert(3)">Niebezpieczny</a>'
        .'<a href="h&#x74;tp&#x73;://example.test">Bezpieczny</a>'
        .'<span><svg><script>alert(4)</script></svg><strong>Zażółć</strong></span>'
        .'</section></span></div>';

    $sanitized = $sanitizer->sanitize($input);

    expect($sanitized)->toBe(
        '<p>Widoczny</p><a>Niebezpieczny</a><a href="https://example.test">Bezpieczny</a><strong>Zażółć</strong>'
    )->and($sanitized)->not->toContain('onclick')
        ->and($sanitized)->not->toContain('onmouseover')
        ->and($sanitized)->not->toContain('<svg')
        ->and($sanitized)->not->toContain('<script');
});

it('keeps safe output stable for malformed html and mixed URL schemes', function (): void {
    $sanitizer = new RichTextSanitizer;
    $corpus = [
        '<div><span>Tekst<br></span></div>',
        '<p><a href="JAVASCRIPT:alert(1)">Nie</a><a href="//evil.test">Nie</a><a href="/lokal">Tak</a></p>',
        '<p><a href="mailto:kontakt@example.test">Mail</a> <a href="https://example.test">HTTPS</a></p>',
        '<div><p>Niepoprawne <strong>HTML</div>',
        '<template><p>Ukryty</p></template><p>Widoczny</p>',
    ];

    foreach ($corpus as $html) {
        $once = $sanitizer->sanitize($html);

        expect($sanitizer->sanitize($once))->toBe($once)
            ->and($once)->not->toContain('javascript:')
            ->and($once)->not->toContain('//evil.test');
    }
});

it('normalizes empty content and rejects oversized or non-string values', function (): void {
    $field = RichTextField::make('description')->maxBytes(32);

    expect($field->prepareForValidation(' <p><br></p> '))->toBeNull()
        ->and(fn () => $field->normalizeValue(['invalid']))->toThrow(InvalidArgumentException::class);

    $validator = Validator::make(
        ['description' => str_repeat('x', 33)],
        ['description' => $field->defaultValidationRules()],
    );

    expect($validator->fails())->toBeTrue();
});

it('exposes explicit schema, import format and safe display output', function (): void {
    $field = RichTextField::make('description')->maxBytes(4096);

    expect($field->getType())->toBe('rich-text')
        ->and($field->getStorageFormat())->toBe('sanitized-html')
        ->and($field->getSchema())->toMatchArray([
            'type' => 'string',
            'contentMediaType' => 'text/html',
            'x-seviaflow-richtext' => [
                'format' => 'sanitized-html',
                'allowedElements' => ['p', 'h2', 'h3', 'strong', 'em', 'ul', 'ol', 'li', 'a', 'br'],
                'allowedProtocols' => ['http', 'https', 'mailto'],
                'maxBytes' => 4096,
            ],
        ])
        ->and($field->prepareForImport('<div><span onclick="alert(1)"><p>Import</p></span></div>'))
        ->toBe('<p>Import</p>')
        ->and($field->sanitizeForDisplay('<p>Read</p><img src=x onerror=alert(1)>'))->toBe('<p>Read</p>');
});

it('keeps the rich text editor value as a string during form initialization', function (): void {
    $field = RichTextField::make('description');

    expect((new FormStateInitializer)->initialize([$field], null))->toBe(['description' => '']);
});
