<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources;

use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Table;
use Tests\Fixtures\Models\ExampleUser;
use Tests\Fixtures\Resources\Tables\ExampleUserTable;

final class ExtractedExampleUserResource extends Resource
{
    protected static string $model = ExampleUser::class;

    public static function table(Table $table): Table
    {
        return ExampleUserTable::configure($table);
    }
}
