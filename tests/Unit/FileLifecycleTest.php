<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use SeviaFlow\Core\Files\FileLifecycleManager;
use SeviaFlow\Core\Files\FileMutation;
use SeviaFlow\Core\Operations\ResourceOperationContext;
use SeviaFlow\Core\Operations\ResourceOperationService;
use SeviaFlow\Core\Resources\Fields\FileField;
use SeviaFlow\Core\Resources\Fields\TextField;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceContext;

beforeEach(function (): void {
    Schema::create('file_lifecycle_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('document_path')->nullable();
        $table->timestamps();
    });
});

it('uses an explicit file mutation contract', function (): void {
    expect(FileMutation::from(null)->action())->toBe(FileMutation::RETAIN)
        ->and(FileMutation::from('remove')->action())->toBe(FileMutation::REMOVE)
        ->and(fn () => FileMutation::from('documents/unsafe.pdf'))
        ->toThrow(InvalidArgumentException::class, 'explicit retain');
});

it('replaces files only after storing the new value', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('documents/old.pdf', 'old');
    $record = FileLifecycleRecord::query()->create([
        'name' => 'Replacement',
        'document_path' => 'documents/old.pdf',
    ]);

    $service = new ResourceOperationService;
    $service->execute(
        resourceClass: FileLifecycleResource::class,
        resourceSlug: 'file-lifecycle',
        model: $record,
        fields: FileLifecycleResource::form(new Form)->getAllFields(),
        values: ['document_path' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf')],
        context: ResourceContext::Edit,
        operationContext: new ResourceOperationContext(source: 'form'),
    );

    $record->refresh();
    $newPath = (string) $record->document_path;

    Storage::disk('public')->assertMissing('documents/old.pdf');
    Storage::disk('public')->assertExists($newPath);
    expect($newPath)->not->toBe('documents/old.pdf');
});

it('keeps the old file and cleans the new file when saving fails', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('documents/old.pdf', 'old');
    $record = FileLifecycleRecord::query()->create([
        'name' => 'Failed replacement',
        'document_path' => 'documents/old.pdf',
    ]);

    expect(fn () => (new ResourceOperationService)->execute(
        resourceClass: FileLifecycleResource::class,
        resourceSlug: 'file-lifecycle',
        model: $record,
        fields: FileLifecycleResource::form(new Form)->getAllFields(),
        values: ['document_path' => UploadedFile::fake()->create('failed.pdf', 10, 'application/pdf')],
        context: ResourceContext::Edit,
        operationContext: new ResourceOperationContext(source: 'form'),
        save: static function (Model $model): void {
            throw new RuntimeException('save failed');
        },
    ))->toThrow(RuntimeException::class, 'save failed');

    Storage::disk('public')->assertExists('documents/old.pdf');
    expect(Storage::disk('public')->files('documents'))->toBe(['documents/old.pdf']);
});

it('cleans files when an enclosing transaction rolls back', function (): void {
    Storage::fake('public');
    $fields = FileLifecycleResource::form(new Form)->getAllFields();
    $fileLifecycle = new FileLifecycleManager($fields);

    expect(fn () => DB::transaction(function () use ($fields, $fileLifecycle): void {
        (new ResourceOperationService)->execute(
            resourceClass: FileLifecycleResource::class,
            resourceSlug: 'file-lifecycle',
            model: new FileLifecycleRecord,
            fields: $fields,
            values: ['name' => 'Batch file', 'document_path' => UploadedFile::fake()->create('batch.pdf', 10, 'application/pdf')],
            context: ResourceContext::Create,
            operationContext: new ResourceOperationContext(source: 'import'),
            fileLifecycle: $fileLifecycle,
        );

        throw new RuntimeException('batch failed');
    }))->toThrow(RuntimeException::class, 'batch failed');

    $fileLifecycle->rollback();

    expect(Storage::disk('public')->files('documents'))->toBe([]);
});

it('does not remove a shared file until the last reference is removed', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('documents/shared.pdf', 'shared');
    $first = FileLifecycleRecord::query()->create(['name' => 'First', 'document_path' => 'documents/shared.pdf']);
    $second = FileLifecycleRecord::query()->create(['name' => 'Second', 'document_path' => 'documents/shared.pdf']);
    $fields = FileLifecycleResource::form(new Form)->getAllFields();

    (new ResourceOperationService)->execute(
        resourceClass: FileLifecycleResource::class,
        resourceSlug: 'file-lifecycle',
        model: $first,
        fields: $fields,
        values: ['document_path' => 'remove'],
        context: ResourceContext::Edit,
        operationContext: new ResourceOperationContext(source: 'form'),
    );

    Storage::disk('public')->assertExists('documents/shared.pdf');

    (new ResourceOperationService)->execute(
        resourceClass: FileLifecycleResource::class,
        resourceSlug: 'file-lifecycle',
        model: $second,
        fields: $fields,
        values: ['document_path' => 'remove'],
        context: ResourceContext::Edit,
        operationContext: new ResourceOperationContext(source: 'form'),
    );

    Storage::disk('public')->assertMissing('documents/shared.pdf');
});

it('can retry removal without failing after the file is gone', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('documents/removable.pdf', 'file');
    $record = FileLifecycleRecord::query()->create(['name' => 'Retry', 'document_path' => 'documents/removable.pdf']);
    $fields = FileLifecycleResource::form(new Form)->getAllFields();

    (new ResourceOperationService)->execute(
        resourceClass: FileLifecycleResource::class,
        resourceSlug: 'file-lifecycle',
        model: $record,
        fields: $fields,
        values: ['document_path' => 'remove'],
        context: ResourceContext::Edit,
        operationContext: new ResourceOperationContext(source: 'form'),
    );

    (new ResourceOperationService)->execute(
        resourceClass: FileLifecycleResource::class,
        resourceSlug: 'file-lifecycle',
        model: $record->fresh(),
        fields: $fields,
        values: ['document_path' => 'remove'],
        context: ResourceContext::Edit,
        operationContext: new ResourceOperationContext(source: 'form'),
    );

    Storage::disk('public')->assertMissing('documents/removable.pdf');
});

it('rejects an arbitrary disk path', function (): void {
    Storage::fake('public');
    $record = FileLifecycleRecord::query()->create([
        'name' => 'Unsafe path',
        'document_path' => 'documents/old.pdf',
    ]);

    expect(fn () => (new ResourceOperationService)->execute(
        resourceClass: FileLifecycleResource::class,
        resourceSlug: 'file-lifecycle',
        model: $record,
        fields: FileLifecycleResource::form(new Form)->getAllFields(),
        values: ['document_path' => '../foreign.pdf'],
        context: ResourceContext::Edit,
        operationContext: new ResourceOperationContext(source: 'api'),
    ))->toThrow(InvalidArgumentException::class, 'explicit retain');
});

it('requires an authorization callback for private file reads', function (): void {
    $field = FileField::make('document_path')->visibility(FileField::VISIBILITY_PRIVATE);
    $record = new FileLifecycleRecord;

    expect($field->canRead('operator', $record))->toBeFalse()
        ->and($field->authorizeRead(static fn (Model $record, mixed $user): bool => $user === 'operator')
            ->canRead('operator', $record))->toBeTrue()
        ->and($field->canRead('guest', $record))->toBeFalse();
});

/**
 * @property string|null $document_path
 */
final class FileLifecycleRecord extends Model
{
    protected $table = 'file_lifecycle_records';

    protected $guarded = [];
}

final class FileLifecycleResource extends Resource
{
    protected static string $model = FileLifecycleRecord::class;

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name'),
            FileField::make('document_path')
                ->disk('public')
                ->directory('documents')
                ->acceptedFileTypes(['application/pdf']),
        ]);
    }
}
