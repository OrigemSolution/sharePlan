<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UtilityResource\Pages;
use App\Filament\Resources\UtilityResource\RelationManagers;
use App\Models\Utility;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Model;

class UtilityResource extends Resource
{
    protected static ?string $model = Utility::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Others';

    protected static ?int $navigationSort = 6;

    public static function canCreate(): bool
    {
        // Prevent creating new transactions
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        // Prevent deleting transactions
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('creator_percentage')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('flat_fee')
                    ->required()
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('creator_percentage')
                    ->searchable(),
                Tables\Columns\TextColumn::make('flat_fee')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListUtilities::route('/'),
            // 'create' => Pages\CreateUtility::route('/create'),
            'edit' => Pages\EditUtility::route('/{record}/edit'),
        ];
    }
}
