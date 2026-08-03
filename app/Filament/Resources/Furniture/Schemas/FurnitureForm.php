<?php

namespace App\Filament\Resources\Furniture\Schemas;

use App\Enums\FurnitureStyle;
use App\Enums\FurnitureType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FurnitureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Название')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label('Тип мебели')
                    ->options(FurnitureType::options())
                    ->required(),
                TextInput::make('price')
                    ->label('Цена')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->suffix('₽'),
                TextInput::make('room_size')
                    ->label('Помещение/размер')
                    ->required()
                    ->maxLength(255),
                Select::make('style')
                    ->label('Стиль')
                    ->options(FurnitureStyle::options())
                    ->required(),
            ]);
    }
}
