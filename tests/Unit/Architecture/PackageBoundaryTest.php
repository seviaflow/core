<?php

declare(strict_types=1);

it('core has no UI API DevTools or Livewire production dependency', function (): void {
    $root = dirname(__DIR__, 3);
    $composer = json_decode((string) file_get_contents($root.'/composer.json'), true);

    $requirements = $composer['require'] ?? [];

    expect(array_key_exists('livewire/livewire', $requirements))->toBeFalse();
    expect(array_key_exists('seviaflow/ui', $requirements))->toBeFalse();
    expect(array_key_exists('seviaflow/api', $requirements))->toBeFalse();
    expect(array_key_exists('seviaflow/devtools', $requirements))->toBeFalse();

    $scan = scanPackageText($root, ['src', 'config', 'database']);

    expect(str_contains($scan, 'SeviaFlow\\Ui'))->toBeFalse();
    expect(str_contains($scan, 'SeviaFlow\\Api'))->toBeFalse();
    expect(str_contains($scan, 'SeviaFlow\\DevTools'))->toBeFalse();
    expect(str_contains($scan, 'Livewire\\'))->toBeFalse();
});

function scanPackageText(string $root, array $directories): string
{
    $buffer = '';

    foreach ($directories as $directory) {
        $path = $root.'/'.$directory;
        if (! is_dir($path)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $buffer .= (string) file_get_contents($file->getPathname());
            }
        }
    }

    return $buffer;
}
