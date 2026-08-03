<?php

namespace App\Filament\Resources\Furniture;

use App\Filament\Resources\Furniture\Pages\CreateFurniture;
use App\Filament\Resources\Furniture\Pages\EditFurniture;
use App\Filament\Resources\Furniture\Pages\ListFurniture;
use App\Filament\Resources\Furniture\Schemas\FurnitureForm;
use App\Filament\Resources\Furniture\Tables\FurnitureTable;
use App\Models\Furniture;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FurnitureResource extends Resource
{
    protected static ?string $model = Furniture::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Мебель';

    protected static ?string $pluralModelLabel = 'Мебель';

    protected static ?string $navigationLabel = 'Мебель';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return FurnitureForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FurnitureTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFurniture::route('/'),
            'create' => CreateFurniture::route('/create'),
            'edit' => EditFurniture::route('/{record}/edit'),
        ];
    }
}
