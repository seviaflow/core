<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Files;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use SeviaFlow\Core\Resources\Fields\Field;
use SeviaFlow\Core\Resources\Fields\FileField;
use Throwable;

final class FileLifecycleManager
{
    /**
     * @var array<int, array{disk:string, path:string}>
     */
    private array $newFiles = [];

    /**
     * @var array<int, array{model:Model, field:FileField, path:string, replacement:string|null}>
     */
    private array $oldFiles = [];

    private bool $finalized = false;

    private bool $cleanupScheduled = false;

    /**
     * @param  array<int, Field>  $fields
     */
    public function __construct(private readonly array $fields = []) {}

    /**
     * @return array{changed:bool, value:mixed}
     */
    public function apply(Model $model, FileField $field, mixed $value): array
    {
        $mutation = FileMutation::from($value);
        if (! $mutation->changesValue()) {
            return ['changed' => false, 'value' => null];
        }

        $oldPath = $this->pathValue($model->getOriginal($field->name()));
        $newPath = null;

        if ($mutation->action() === FileMutation::REPLACE) {
            $upload = $mutation->file();
            if (! $upload instanceof UploadedFile) {
                throw new \InvalidArgumentException('File replace action requires an uploaded file.');
            }

            $newPath = Storage::disk($field->getDisk())->putFile(
                $field->getDirectory(),
                $upload,
                ['visibility' => $field->getVisibility()]
            );

            if (! is_string($newPath) || $newPath === '') {
                throw new \RuntimeException(sprintf(
                    'Unable to store file field [%s].',
                    $field->name()
                ));
            }

            $this->newFiles[] = ['disk' => $field->getDisk(), 'path' => $newPath];
        }

        if ($oldPath !== null && $oldPath !== $newPath) {
            $this->oldFiles[] = [
                'model' => $model,
                'field' => $field,
                'path' => $oldPath,
                'replacement' => $newPath,
            ];
        }

        return ['changed' => true, 'value' => $newPath];
    }

    public function finalize(Model $model): void
    {
        if ($this->finalized || $this->cleanupScheduled) {
            return;
        }

        $this->cleanupScheduled = true;
        $cleanup = function (): void {
            $this->finalized = true;

            foreach ($this->oldFiles as $oldFile) {
                $this->deleteOldFile($oldFile['model'], $oldFile['field'], $oldFile['path'], $oldFile['replacement']);
            }
        };

        if ($model->getConnection()->transactionLevel() > 0) {
            $model->getConnection()->afterCommit($cleanup);

            return;
        }

        $cleanup();
    }

    public function rollback(): void
    {
        if ($this->finalized) {
            return;
        }

        foreach ($this->newFiles as $newFile) {
            try {
                Storage::disk($newFile['disk'])->delete($newFile['path']);
            } catch (Throwable) {
            }
        }

        $this->newFiles = [];
    }

    private function deleteOldFile(Model $model, FileField $field, string $path, ?string $replacement): void
    {
        if ($path === $replacement || ! FilePathGuard::isManaged($field, $path) || $this->isReferencedByAnotherRecord($model, $path)) {
            return;
        }

        try {
            Storage::disk($field->getDisk())->delete($path);
        } catch (Throwable) {
        }
    }

    private function isReferencedByAnotherRecord(Model $model, string $path): bool
    {
        $fileFields = array_values(array_filter(
            $this->fields,
            static fn (Field $field): bool => $field instanceof FileField
        ));

        if ($fileFields === []) {
            return false;
        }

        try {
            $query = $model->newQuery();
            if ($model->exists) {
                $query->whereKeyNot($model->getKey());
            }

            $query->where(function ($nestedQuery) use ($fileFields, $path): void {
                foreach ($fileFields as $index => $fileField) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $nestedQuery->{$method}($fileField->name(), $path);
                }
            });

            return $query->exists();
        } catch (Throwable) {
            return true;
        }
    }

    private function pathValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
