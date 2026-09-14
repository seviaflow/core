<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Files;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

final class FileMutation
{
    public const RETAIN = 'retain';

    public const REPLACE = 'replace';

    public const REMOVE = 'remove';

    private function __construct(
        private readonly string $action,
        private readonly ?UploadedFile $file = null
    ) {}

    public static function from(mixed $value): self
    {
        if ($value === null || $value === self::RETAIN) {
            return new self(self::RETAIN);
        }

        if ($value === self::REMOVE) {
            return new self(self::REMOVE);
        }

        if ($value instanceof UploadedFile) {
            return new self(self::REPLACE, $value);
        }

        if (! is_array($value) || ! array_key_exists('action', $value)) {
            throw new InvalidArgumentException(
                'File value must be an upload or an explicit retain, replace, or remove action.'
            );
        }

        $action = $value['action'];
        if (! is_string($action) || ! in_array($action, [self::RETAIN, self::REPLACE, self::REMOVE], true)) {
            throw new InvalidArgumentException('Unsupported file action. Use retain, replace, or remove.');
        }

        if ($action === self::RETAIN || $action === self::REMOVE) {
            if (count($value) !== 1) {
                throw new InvalidArgumentException('File retain and remove actions cannot contain extra values.');
            }

            return new self($action);
        }

        if (count($value) !== 2 || ! array_key_exists('file', $value) || ! $value['file'] instanceof UploadedFile) {
            throw new InvalidArgumentException('File replace action requires an uploaded file.');
        }

        return new self(self::REPLACE, $value['file']);
    }

    public function action(): string
    {
        return $this->action;
    }

    public function file(): ?UploadedFile
    {
        return $this->file;
    }

    public function changesValue(): bool
    {
        return $this->action !== self::RETAIN;
    }
}
