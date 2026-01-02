<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceResource\Pages;
use App\Filament\Resources\ServiceResource\RelationManagers;
use App\Filament\Resources\ServiceResource\RelationManagers\SlotsRelationManager;
use App\Models\Service;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;


class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationGroup = 'Family Plan';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('description')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('max_members')
                    ->required()
                    ->numeric(),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
                Forms\Components\FileUpload::make('logo')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->searchable()
                    ->money('NGN')
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_members')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_active'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->options([
                        true => 'Active',
                        false => 'Inactive',
                    ]),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('name')
                    ->label('Name'),
                TextEntry::make('description')
                    ->label('Description'),
                TextEntry::make('price')
                    ->label('Price')
                    ->money('NGN'),
                TextEntry::make('max_members')
                    ->label('Max Members'),
                ImageEntry::make('logo')
                    ->label('Logo')
                    ->circular(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SlotsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'view' => Pages\ViewService::route('/{record}'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
