<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Enums\FurnitureType;
use App\Enums\LeadStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('furniture_type')
                    ->label('Тип мебели')
                    ->formatStateUsing(
                        fn (?string $state): ?string => $state === null
                            ? null
                            : (FurnitureType::tryFrom($state)?->label() ?? $state),
                    ),
                TextColumn::make('budget')
                    ->label('Бюджет')
                    ->money('RUB', locale: 'ru', decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Имя')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->formatStateUsing(fn (LeadStatus $state): string => $state->label())
                    ->badge()
                    ->sortable(),
                TextColumn::make('assignedManager.name')
                    ->label('Назначенный менеджер'),
                TextColumn::make('created_at')
                    ->label('Дата создания')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(LeadStatus::options()),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', direction: 'desc');
    }
}
