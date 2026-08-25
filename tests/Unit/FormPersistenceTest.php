<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use SeviaFlow\Core\Forms\FormPersistence;
use SeviaFlow\Core\Resources\Fields\PasswordField;
use SeviaFlow\Core\Resources\Fields\SelectField;
use SeviaFlow\Core\Resources\Fields\TextField;
use SeviaFlow\Core\Resources\ResourceContext;

beforeEach(function (): void {
    Schema::create('form_persistence_users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('status')->nullable();
        $table->string('password')->nullable();
        $table->string('slug')->nullable();
        $table->text('internal_notes')->nullable();
        $table->timestamps();
    });
});

it('persists only visible editable fields in edit context', function (): void {
    /** @var FormPersistenceUser $model */
    $model = FormPersistenceUser::query()->create([
        'name' => 'Original Name',
        'status' => 'draft',
        'slug' => 'original-slug',
        'internal_notes' => 'private note',
    ]);

    $fields = [
        TextField::make('name')->label('Name'),
        SelectField::make('status')->options([
            'draft' => 'Draft',
            'published' => 'Published',
        ])->disabledOnEdit(),
        TextField::make('slug')->readonlyOnEdit(),
        TextField::make('internal_notes')->hiddenOnEdit(),
    ];

    /** @var FormPersistenceUser $persisted */
    $persisted = (new FormPersistence)->persist(
        $model,
        $fields,
        [
            'name' => 'Updated Name',
            'status' => 'published',
            'slug' => 'tampered-slug',
            'internal_notes' => 'tampered note',
        ],
        ResourceContext::Edit
    );

    $persisted->refresh();

    expect($persisted->name)->toBe('Updated Name')
        ->and($persisted->status)->toBe('draft')
        ->and($persisted->slug)->toBe('original-slug')
        ->and($persisted->internal_notes)->toBe('private note');
});

it('hashes password and does not overwrite with empty value on edit', function (): void {
    /** @var FormPersistenceUser $model */
    $model = FormPersistenceUser::query()->create([
        'name' => 'Password Persist',
        'password' => Hash::make('old-secret'),
    ]);
    $oldHash = (string) $model->password;

    $fields = [
        PasswordField::make('password')->label('Password'),
    ];

    (new FormPersistence)->persist(
        $model,
        $fields,
        ['password' => 'new-secret'],
        ResourceContext::Edit
    );

    $model->refresh();
    expect(Hash::check('new-secret', (string) $model->password))->toBeTrue()
        ->and((string) $model->password)->not->toBe('new-secret');

    (new FormPersistence)->persist(
        $model,
        $fields,
        ['password' => ''],
        ResourceContext::Edit
    );

    $model->refresh();
    expect((string) $model->password)->not->toBe($oldHash)
        ->and(Hash::check('new-secret', (string) $model->password))->toBeTrue();
});

/**
 * @property string|null $name
 * @property string|null $status
 * @property string|null $slug
 * @property string|null $internal_notes
 * @property string|null $password
 */
final class FormPersistenceUser extends Model
{
    protected $table = 'form_persistence_users';

    protected $guarded = [];
}
