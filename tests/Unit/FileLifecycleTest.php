<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
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
        $table->string('thumbnail_path')->nullable();
        $table->string('archive_path')->nullable();
        $table->softDeletes();
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

it('keeps a file referenced by another file field on the same record', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('documents/same-record.pdf', 'shared');
    $record = FileLifecycleRecord::query()->create([
        'name' => 'Same record',
        'document_path' => 'documents/same-record.pdf',
        'thumbnail_path' => 'documents/same-record.pdf',
    ]);
    $fields = FileLifecycleResource::form(new Form)->getAllFields();

    (new ResourceOperationService)->execute(
        resourceClass: FileLifecycleResource::class,
        resourceSlug: 'file-lifecycle',
        model: $record,
        fields: $fields,
        values: ['document_path' => UploadedFile::fake()->create('replacement.pdf', 10, 'application/pdf')],
        context: ResourceContext::Edit,
        operationContext: new ResourceOperationContext(source: 'form'),
    );

    Storage::disk('public')->assertExists('documents/same-record.pdf');
    $record->refresh();
    Storage::disk('public')->assertExists($record->document_path);

    (new ResourceOperationService)->execute(
        resourceClass: FileLifecycleResource::class,
        resourceSlug: 'file-lifecycle',
        model: $record->fresh(),
        fields: $fields,
        values: ['thumbnail_path' => 'remove'],
        context: ResourceContext::Edit,
        operationContext: new ResourceOperationContext(source: 'form'),
    );

    Storage::disk('public')->assertMissing('documents/same-record.pdf');
});

it('uses the full resource catalog when the write fields are partial', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('documents/partial-catalog.pdf', 'shared');
    $record = FileLifecycleRecord::query()->create([
        'name' => 'Partial catalog',
        'document_path' => 'documents/partial-catalog.pdf',
        'thumbnail_path' => 'documents/partial-catalog.pdf',
    ]);
    $documentField = FileLifecycleResource::form(new Form)->getAllFields()[1];
    $thumbnailField = FileLifecycleResource::form(new Form)->getAllFields()[2];

    (new ResourceOperationService)->execute(
        resourceClass: FileLifecycleResource::class,
        resourceSlug: 'file-lifecycle',
        model: $record,
        fields: [$documentField],
        values: ['document_path' => 'remove'],
        context: ResourceContext::Edit,
        operationContext: new ResourceOperationContext(source: 'api'),
    );

    Storage::disk('public')->assertExists('documents/partial-catalog.pdf');
    expect($record->fresh()?->document_path)->toBeNull();

    (new ResourceOperationService)->execute(
        resourceClass: FileLifecycleResource::class,
        resourceSlug: 'file-lifecycle',
        model: $record->fresh(),
        fields: [$thumbnailField],
        values: ['thumbnail_path' => 'remove'],
        context: ResourceContext::Edit,
        operationContext: new ResourceOperationContext(source: 'api'),
    );

    Storage::disk('public')->assertMissing('documents/partial-catalog.pdf');
});

it('does not treat the same path on another disk as a reference', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Storage::disk('public')->put('documents/same-name.pdf', 'public');
    Storage::disk('local')->put('documents/same-name.pdf', 'local');
    $record = FileLifecycleRecord::query()->create([
        'name' => 'Different disks',
        'document_path' => 'documents/same-name.pdf',
        'archive_path' => 'documents/same-name.pdf',
    ]);

    (new ResourceOperationService)->execute(
        resourceClass: FileLifecycleResource::class,
        resourceSlug: 'file-lifecycle',
        model: $record,
        fields: FileLifecycleResource::form(new Form)->getAllFields(),
        values: ['document_path' => 'remove'],
        context: ResourceContext::Edit,
        operationContext: new ResourceOperationContext(source: 'form'),
    );

    Storage::disk('public')->assertMissing('documents/same-name.pdf');
    Storage::disk('local')->assertExists('documents/same-name.pdf');
});

it('keeps files referenced by soft-deleted records until the last reference is removed', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('documents/trashed.pdf', 'shared');
    $active = FileLifecycleRecord::query()->create([
        'name' => 'Active',
        'document_path' => 'documents/trashed.pdf',
    ]);
    $trashed = FileLifecycleRecord::query()->create([
        'name' => 'Trashed',
        'document_path' => 'documents/trashed.pdf',
    ]);
    $trashed->delete();

    (new ResourceOperationService)->execute(
        resourceClass: FileLifecycleResource::class,
        resourceSlug: 'file-lifecycle',
        model: $active,
        fields: FileLifecycleResource::form(new Form)->getAllFields(),
        values: ['document_path' => 'remove'],
        context: ResourceContext::Edit,
        operationContext: new ResourceOperationContext(source: 'form'),
    );

    Storage::disk('public')->assertExists('documents/trashed.pdf');

    $trashed->restore();
    (new ResourceOperationService)->execute(
        resourceClass: FileLifecycleResource::class,
        resourceSlug: 'file-lifecycle',
        model: $trashed->fresh(),
        fields: FileLifecycleResource::form(new Form)->getAllFields(),
        values: ['document_path' => 'remove'],
        context: ResourceContext::Edit,
        operationContext: new ResourceOperationContext(source: 'form'),
    );

    Storage::disk('public')->assertMissing('documents/trashed.pdf');
});

it('cleans new files when an enclosing transaction rolls back without manual cleanup', function (): void {
    Storage::fake('public');
    $fileLifecycle = new FileLifecycleManager(FileLifecycleResource::form(new Form)->getAllFields());

    expect(fn () => DB::transaction(function () use ($fileLifecycle): void {
        (new ResourceOperationService)->execute(
            resourceClass: FileLifecycleResource::class,
            resourceSlug: 'file-lifecycle',
            model: new FileLifecycleRecord,
            fields: FileLifecycleResource::form(new Form)->getAllFields(),
            values: [
                'name' => 'Outer rollback',
                'document_path' => UploadedFile::fake()->create('outer.pdf', 10, 'application/pdf'),
            ],
            context: ResourceContext::Create,
            operationContext: new ResourceOperationContext(source: 'form'),
            fileLifecycle: $fileLifecycle,
        );

        throw new RuntimeException('outer transaction failed');
    }))->toThrow(RuntimeException::class, 'outer transaction failed');

    expect(Storage::disk('public')->files('documents'))->toBe([]);
});

it('keeps a file and logs a safe diagnostic when reference lookup fails', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('documents/unknown-reference.pdf', 'keep');
    /** @var MockInterface $logger */
    $logger = Log::spy();
    $field = FileField::make('document_path')->disk('public')->directory('documents');
    $record = new BrokenFileLifecycleRecord;
    $record->forceFill(['id' => 1, 'document_path' => 'documents/unknown-reference.pdf']);
    $record->syncOriginal();
    $manager = new FileLifecycleManager([$field]);

    $manager->apply($record, $field, 'remove');
    $manager->finalize($record);

    Storage::disk('public')->assertExists('documents/unknown-reference.pdf');
    $logger->shouldHaveReceived('warning')->withArgs(
        static fn (string $message, array $context): bool => $message === 'File reference lookup failed; preserving file.'
            && $context['model'] === BrokenFileLifecycleRecord::class
            && $context['field'] === 'document_path'
    )->once();
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
    use SoftDeletes;

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
            FileField::make('thumbnail_path')
                ->disk('public')
                ->directory('documents'),
            FileField::make('archive_path')
                ->disk('local')
                ->directory('documents'),
        ]);
    }
}

final class BrokenFileLifecycleRecord extends Model
{
    protected $table = 'missing_file_lifecycle_records';

    protected $guarded = [];
}
