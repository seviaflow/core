<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources;

use SeviaFlow\Core\Resources\Columns\BooleanColumn;
use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Core\Resources\Filters\BooleanFilter;
use SeviaFlow\Core\Resources\Filters\SelectFilter;
use SeviaFlow\Core\Resources\Resource;
use SeviaFlow\Core\Resources\Table;
use Tests\Fixtures\Models\ExampleUser;

final class InlineExampleUserResource extends Resource
{
    protected static string $model = ExampleUser::class;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable(),
                BooleanColumn::make('is_active')->label('Active')->sortable(),
                TextColumn::make('status')->label('Status'),
            ])
            ->filters([
                BooleanFilter::make('is_active')->label('Active'),
                SelectFilter::make('status')->label('Status')->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                ]),
            ]);
    }
}
