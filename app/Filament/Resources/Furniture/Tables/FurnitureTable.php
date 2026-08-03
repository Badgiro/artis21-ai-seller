<?php

namespace App\Filament\Resources\Furniture\Tables;

use App\Enums\FurnitureStyle;
use App\Enums\FurnitureType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FurnitureTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Тип мебели')
                    ->formatStateUsing(fn (FurnitureType $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('price')
                    ->label('Цена')
                    ->money('RUB', locale: 'ru', decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('room_size')
                    ->label('Помещение/размер'),
                TextColumn::make('style')
                    ->label('Стиль')
                    ->formatStateUsing(fn (FurnitureStyle $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Дата создания')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Тип мебели')
                    ->options(FurnitureType::options()),
                SelectFilter::make('style')
                    ->label('Стиль')
                    ->options(FurnitureStyle::options()),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
