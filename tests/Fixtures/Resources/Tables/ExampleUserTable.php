<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\Tables;

use SeviaFlow\Core\Resources\Columns\BooleanColumn;
use SeviaFlow\Core\Resources\Columns\TextColumn;
use SeviaFlow\Core\Resources\Filters\BooleanFilter;
use SeviaFlow\Core\Resources\Filters\SelectFilter;
use SeviaFlow\Core\Resources\Table;

final class ExampleUserTable
{
    public static function configure(Table $table): Table
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
