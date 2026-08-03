<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\FurnitureStyle;
use App\Enums\FurnitureType;
use App\Enums\LeadStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('furniture_type')
                    ->label('Тип мебели')
                    ->options(FurnitureType::options()),
                TextInput::make('budget')
                    ->label('Бюджет')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('₽'),
                TextInput::make('room_size')
                    ->label('Помещение/размер')
                    ->maxLength(255),
                Select::make('style')
                    ->label('Стиль')
                    ->options(FurnitureStyle::options()),
                TextInput::make('timeline')
                    ->label('Сроки')
                    ->maxLength(255),
                TextInput::make('city')
                    ->label('Город')
                    ->maxLength(255),
                TextInput::make('name')
                    ->label('Имя')
                    ->required()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->label('Телефон')
                    ->tel()
                    ->required()
                    ->maxLength(255),
                Select::make('status')
                    ->label('Статус')
                    ->options(LeadStatus::options())
                    ->default(LeadStatus::New->value)
                    ->required(),
                Select::make('assigned_user_id')
                    ->label('Назначенный менеджер')
                    ->relationship(name: 'assignedManager', titleAttribute: 'name')
                    ->searchable()
                    ->preload(),
            ]);
    }
}
