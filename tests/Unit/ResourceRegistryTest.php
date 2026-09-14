<?php

declare(strict_types=1);

use SeviaFlow\Core\Exceptions\InvalidResourceClassException;
use SeviaFlow\Core\Exceptions\ResourceSlugNotRegisteredException;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\ResourceRegistry;
use SeviaFlow\Core\Resources\Table;
use Tests\Fixtures\Models\ExampleUser;

it('resolves registered resource class by slug', function (): void {
    $registry = new ResourceRegistry([
        'users' => RegistryTestUserResource::class,
    ]);

    expect($registry->resolve('users'))->toBe(RegistryTestUserResource::class);
});

it('throws for unknown resource slug', function (): void {
    $registry = new ResourceRegistry([
        'users' => RegistryTestUserResource::class,
    ]);

    $registry->resolve('unknown');
})->throws(ResourceSlugNotRegisteredException::class);

it('does not resolve raw class name as resource slug', function (): void {
    $registry = new ResourceRegistry([
        'users' => RegistryTestUserResource::class,
    ]);

    $registry->resolve(RegistryTestUserResource::class);
})->throws(ResourceSlugNotRegisteredException::class);

it('rejects invalid registered resource class', function (): void {
    $registry = new ResourceRegistry([
        'users' => stdClass::class,
    ]);

    $registry->resolve('users');
})->throws(InvalidResourceClassException::class);

it('finds the registered resource for a model', function (): void {
    $registry = new ResourceRegistry([
        'users' => RegistryTestUserResource::class,
    ]);

    expect($registry->findByModel(ExampleUser::class))->toBe(RegistryTestUserResource::class)
        ->and($registry->findByModel(stdClass::class))->toBeNull();
});

final class RegistryTestUserResource extends Resource
{
    protected static string $model = ExampleUser::class;

    public static function table(Table $table): Table
    {
        return $table;
    }
}
