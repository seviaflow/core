<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use SeviaFlow\Core\Resources\Action;
use SeviaFlow\Core\Resources\ApiResource;
use SeviaFlow\Core\Resources\BulkAction;
use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Core\Resources\ExportField;
use SeviaFlow\Core\Resources\ExportResource;
use SeviaFlow\Core\Resources\Fields\TextField;
use SeviaFlow\Core\Resources\Filters\SelectFilter;
use SeviaFlow\Core\Resources\Form;
use SeviaFlow\Core\Resources\ImportResource;
use SeviaFlow\Core\Resources\Metadata\ResourceMetadataCache;
use SeviaFlow\Core\Resources\Metadata\ResourceMetadataResolver;
use SeviaFlow\Core\Resources\Relations\HasManyRelation;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceRegistry;
use SeviaFlow\Core\Resources\Table;
use SeviaFlow\Core\Resources\Workflows\State;
use SeviaFlow\Core\Resources\Workflows\Transition;
use SeviaFlow\Core\Resources\Workflows\Workflow;

it('metadata resolver returns stable resource metadata snapshot', function (): void {
    $resolver = new ResourceMetadataResolver;
    $snapshot = $resolver->resolve('metadata-users', MetadataUsersResource::class)->toArray();

    expect($snapshot['slug'])->toBe('metadata-users')
        ->and($snapshot['resource_class'])->toBe(MetadataUsersResource::class)
        ->and($snapshot['model_class'])->toBe(MetadataUser::class)
        ->and($snapshot['navigation']['label'])->toBe('Users')
        ->and($snapshot['table']['columns'][0]['name'])->toBe('name')
        ->and($snapshot['api']['enabled'])->toBeTrue()
        ->and($snapshot['export']['enabled'])->toBeTrue()
        ->and($snapshot['import']['enabled'])->toBeTrue();
});

it('metadata cache stores result when enabled', function (): void {
    config()->set('seviaflow.performance.cache.enabled', true);
    config()->set('seviaflow.performance.cache.resources', true);
    config()->set('seviaflow.performance.cache.store', 'array');
    flushArrayCache();

    $cache = new ResourceMetadataCache(
        new ResourceRegistry(['metadata-users' => MetadataUsersResource::class])
    );

    $first = $cache->forSlug('metadata-users')->toArray();
    $second = $cache->forSlug('metadata-users')->toArray();

    expect(cache()->store('array')->has(resourceMetadataCacheKey('metadata-users', MetadataUsersResource::class)))->toBeTrue()
        ->and($first)->toBe($second);
});

it('metadata cache bypasses store when disabled', function (): void {
    config()->set('seviaflow.performance.cache.enabled', false);
    config()->set('seviaflow.performance.cache.resources', true);

    $cache = new ResourceMetadataCache(
        new ResourceRegistry(['metadata-users' => MetadataUsersResource::class])
    );

    $first = $cache->forSlug('metadata-users')->toArray();
    $second = $cache->forSlug('metadata-users')->toArray();

    expect(cache()->store('array')->has(resourceMetadataCacheKey('metadata-users', MetadataUsersResource::class)))->toBeFalse()
        ->and($first)->toBe($second);
});

it('cache clear removes cached resource metadata', function (): void {
    config()->set('seviaflow.performance.cache.enabled', true);
    config()->set('seviaflow.performance.cache.resources', true);
    config()->set('seviaflow.performance.cache.store', 'array');
    flushArrayCache();

    $cache = new ResourceMetadataCache(
        new ResourceRegistry(['metadata-users' => MetadataUsersResource::class])
    );

    $cache->forSlug('metadata-users');
    expect(cache()->store('array')->has(resourceMetadataCacheKey('metadata-users', MetadataUsersResource::class)))->toBeTrue();

    $cache->clear();
    expect(cache()->store('array')->has(resourceMetadataCacheKey('metadata-users', MetadataUsersResource::class)))->toBeFalse();

    $cache->forSlug('metadata-users');

    expect(cache()->store('array')->has(resourceMetadataCacheKey('metadata-users', MetadataUsersResource::class)))->toBeTrue();
});

it('metadata snapshot does not store user-specific authorization decisions', function (): void {
    $snapshot = (new ResourceMetadataResolver)
        ->resolve('metadata-users', MetadataUsersResource::class)
        ->toArray();

    expect($snapshot['actions'][0])->not->toHaveKey('authorization')
        ->and($snapshot['actions'][0])->not->toHaveKey('can_execute')
        ->and($snapshot['bulk_actions'][0])->not->toHaveKey('authorization');
});

it('metadata snapshot never contains result rows or query payloads', function (): void {
    $snapshot = (new ResourceMetadataResolver)
        ->resolve('metadata-users', MetadataUsersResource::class)
        ->toArray();

    expect(json_encode($snapshot))->not->toContain('rows')
        ->and(json_encode($snapshot))->not->toContain('query')
        ->and(json_encode($snapshot))->not->toContain('paginator')
        ->and($snapshot)->not->toHaveKey('data');
});

it('metadata handles resources with closures without serializing closures', function (): void {
    config()->set('seviaflow.performance.cache.enabled', true);
    config()->set('seviaflow.performance.cache.resources', true);
    config()->set('seviaflow.performance.cache.store', 'array');
    flushArrayCache();

    $cache = new ResourceMetadataCache(
        new ResourceRegistry(['metadata-users' => MetadataUsersResource::class]),
        new ResourceMetadataResolver
    );

    $snapshot = $cache->forSlug('metadata-users')->toArray();

    expect(arrayContainsClosure($snapshot))->toBeFalse()
        ->and($snapshot['workflow']['transitions'][0])->not->toHaveKey('handler')
        ->and($snapshot['actions'][0])->not->toHaveKey('handler');
});

/**
 * @param  class-string<resource>  $resourceClass
 */
function resourceMetadataCacheKey(string $slug, string $resourceClass): string
{
    return sprintf('seviaflow:resource-metadata:%s:%s', $slug, sha1($resourceClass));
}

/**
 * @param  array<mixed>  $value
 */
function arrayContainsClosure(array $value): bool
{
    foreach ($value as $item) {
        if ($item instanceof Closure) {
            return true;
        }

        if (is_array($item) && arrayContainsClosure($item)) {
            return true;
        }
    }

    return false;
}

final class MetadataUsersResource extends Resource
{
    protected static string $model = MetadataUser::class;

    protected static ?string $navigationLabel = 'Users';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                ]),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form->fields([
            TextField::make('name')->required(),
        ]);
    }

    public static function actions(): array
    {
        return [
            Action::make('mark_active')
                ->label('Mark Active')
                ->danger()
                ->authorize(fn (mixed $user, mixed $record): bool => $user !== null && $record !== null)
                ->handle(fn (mixed $record): mixed => $record),
        ];
    }

    public static function bulkActions(): array
    {
        return [
            BulkAction::make('archive')
                ->label('Archive')
                ->danger()
                ->authorize(fn (mixed $user): bool => $user !== null)
                ->handle(fn ($records): mixed => $records),
        ];
    }

    public static function relations(): array
    {
        return [
            HasManyRelation::make('logs')
                ->resource(MetadataLogsResource::class)
                ->allowCreate()
                ->allowEdit(),
        ];
    }

    public static function workflow(Workflow $workflow): Workflow
    {
        return $workflow
            ->stateField('status')
            ->states([
                State::make('pending')->label('Pending'),
                State::make('active')->label('Active'),
            ])
            ->transitions([
                Transition::make('approve')
                    ->from('pending')
                    ->to('active')
                    ->visibleIf(fn (): bool => true)
                    ->canRunIf(fn (): bool => true)
                    ->authorize(fn (): bool => true)
                    ->handle(fn (mixed $record): mixed => $record),
            ]);
    }

    public static function api(ApiResource $api): ApiResource
    {
        return $api
            ->enabled()
            ->readable(['id', 'name', 'status'])
            ->creatable(['name'])
            ->updatable(['name'])
            ->deletable()
            ->flow();
    }

    public static function export(ExportResource $export): ExportResource
    {
        return $export
            ->enabled()
            ->allowedFields([
                ExportField::make('id')->label('ID'),
                ExportField::make('name')->label('Name'),
            ])
            ->formats(['csv']);
    }

    public static function import(ImportResource $import): ImportResource
    {
        return $import
            ->enabled()
            ->allowedFields(['name', 'status'])
            ->requiredFields(['name']);
    }
}

final class MetadataLogsResource extends Resource
{
    protected static string $model = MetadataLog::class;
}

final class MetadataUser extends Model
{
    protected $table = 'metadata_users';
}

final class MetadataLog extends Model
{
    protected $table = 'metadata_logs';
}
