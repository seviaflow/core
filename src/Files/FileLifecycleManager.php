<?php

declare(strict_types=1);

namespace SeviaFlow\Core\Files;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
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

    private bool $rolledBack = false;

    private bool $rollbackCallbackRegistered = false;

    /**
     * @param  array<int, Field>  $fields
     * @param  array<int, Field>|null  $referenceFields
     */
    public function __construct(
        private readonly array $fields = [],
        ?array $referenceFields = null,
    ) {
        $this->referenceFields = $referenceFields ?? $fields;
    }

    /**
     * @var array<int, Field>
     */
    private readonly array $referenceFields;

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
            if ($this->rolledBack) {
                return;
            }

            $this->finalized = true;

            foreach ($this->oldFiles as $oldFile) {
                $this->deleteOldFile(
                    $oldFile['model'],
                    $oldFile['field'],
                    $oldFile['path'],
                    $oldFile['replacement']
                );
            }
        };

        if ($model->getConnection()->transactionLevel() > 0) {
            $model->getConnection()->afterCommit($cleanup);
            $this->registerRollbackCallback($model);

            return;
        }

        $cleanup();
    }

    public function registerRollbackCallback(Model $model): void
    {
        if (
            $this->rollbackCallbackRegistered
            || $this->finalized
            || $this->rolledBack
            || $model->getConnection()->transactionLevel() === 0
        ) {
            return;
        }

        if (method_exists($model->getConnection(), 'afterRollBack')) {
            $model->getConnection()->afterRollBack(function (): void {
                $this->rollback();
            });
            $this->rollbackCallbackRegistered = true;

            return;
        }

        Log::warning('File lifecycle rollback callback is unavailable; preserving new files.', [
            'model' => $model::class,
        ]);
    }

    public function rollback(): void
    {
        if ($this->finalized || $this->rolledBack) {
            return;
        }

        $this->rolledBack = true;

        foreach ($this->newFiles as $newFile) {
            try {
                if (! Storage::disk($newFile['disk'])->delete($newFile['path'])) {
                    $this->logCleanupFailure('File rollback cleanup failed; preserving new file.', $newFile['disk']);
                }
            } catch (Throwable $exception) {
                $this->logCleanupFailure(
                    'File rollback cleanup failed; preserving new file.',
                    $newFile['disk'],
                    $exception
                );
            }
        }

        $this->newFiles = [];
        $this->oldFiles = [];
    }

    private function deleteOldFile(Model $model, FileField $field, string $path, ?string $replacement): void
    {
        if (
            $path === $replacement
            || ! FilePathGuard::isManaged($field, $path)
            || $this->isReferenced($model, $field, $path)
        ) {
            return;
        }

        try {
            if (! Storage::disk($field->getDisk())->delete($path)) {
                $this->logCleanupFailure('File cleanup failed; preserving managed file.', $field->getDisk());
            }
        } catch (Throwable $exception) {
            $this->logCleanupFailure(
                'File cleanup failed; preserving managed file.',
                $field->getDisk(),
                $exception
            );
        }
    }

    private function isReferenced(Model $model, FileField $sourceField, string $path): bool
    {
        foreach ($this->fileFieldsForDisk($sourceField) as $fileField) {
            if (
                $fileField->name() !== $sourceField->name()
                && $this->pathValue($model->getAttribute($fileField->name())) === $path
            ) {
                return true;
            }
        }

        $fileFields = $this->fileFieldsForDisk($sourceField);

        if ($fileFields === []) {
            return false;
        }

        try {
            $query = $model->newQuery();
            if ($model->exists) {
                $query->whereKeyNot($model->getKey());
            }

            if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                $query->withoutGlobalScope(SoftDeletingScope::class);
            }

            $query->where(function ($nestedQuery) use ($fileFields, $path): void {
                foreach ($fileFields as $index => $fileField) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $nestedQuery->{$method}($fileField->name(), $path);
                }
            });

            return $query->exists();
        } catch (Throwable $exception) {
            Log::warning('File reference lookup failed; preserving file.', [
                'model' => $model::class,
                'field' => $sourceField->name(),
                'disk' => $sourceField->getDisk(),
                'exception' => $exception::class,
            ]);

            return true;
        }
    }

    /**
     * @return array<int, FileField>
     */
    private function fileFieldsForDisk(FileField $sourceField): array
    {
        return array_values(array_filter(
            $this->referenceFields,
            static fn (Field $field): bool => $field instanceof FileField
                && $field->getDisk() === $sourceField->getDisk()
        ));
    }

    private function logCleanupFailure(string $message, string $disk, ?Throwable $exception = null): void
    {
        Log::warning($message, [
            'disk' => $disk,
            'exception' => $exception instanceof Throwable ? $exception::class : null,
        ]);
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
