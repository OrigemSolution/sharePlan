<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PasswordSharingSlotResource\Pages;
use App\Filament\Resources\PasswordSharingSlotResource\RelationManagers;
use App\Models\PasswordSharingSlot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use App\Filament\Resources\PasswordSharingSlotResource\RelationManagers\MembersRelationManager;

class PasswordSharingSlotResource extends Resource
{
    protected static ?string $model = PasswordSharingSlot::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('password_service_id')
                    ->relationship('PasswordService', 'name')
                    ->required(),
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Forms\Components\TextInput::make('guest_limit')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('current_members')
                    ->required()
                    ->numeric()
                    ->default(1),
                Forms\Components\TextInput::make('duration')
                    ->required()
                    ->numeric(),
                Forms\Components\Select::make('status')
                    ->options([
                        'open' => 'Open',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->required(),
                Forms\Components\Select::make('payment_status')
                    ->options([
                        'paid' => 'Paid',
                        'unpaid' => 'Unpaid',
                    ])
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('PasswordService.name')
                    ->sortable()
                    ->label('Password Service')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable()
                    ->label('User')
                    ->searchable(),
                Tables\Columns\TextColumn::make('guest_limit')
                    ->numeric()
                    ->sortable()
                    ->label('Guest Limit')
                    ->searchable(),
                Tables\Columns\TextColumn::make('current_members')
                    ->numeric()
                    ->sortable()
                    ->label('Current Members')
                    ->searchable(),
                Tables\Columns\TextColumn::make('duration')
                    ->numeric()
                    ->sortable()
                    ->label('Duration')
                    ->suffix(' month(s)')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment Status')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true), 
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                //
            ])
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
                TextEntry::make('PasswordService.name')
                    ->label('Password Service'),
                TextEntry::make('user.name')
                    ->label('User'),
                TextEntry::make('guest_limit')
                    ->label('Guest Limit'),
                TextEntry::make('current_members')
                    ->label('Current Members'),
                TextEntry::make('duration')
                    ->label('Duration')
                    ->suffix(' month(s)'),
                TextEntry::make('status')
                    ->label('Status'),
                TextEntry::make('payment_status')
                    ->label('Payment Status'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // MembersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPasswordSharingSlots::route('/'),
            'create' => Pages\CreatePasswordSharingSlot::route('/create'),
            'view' => Pages\ViewPasswordSharingSlot::route('/{record}'),
            'edit' => Pages\EditPasswordSharingSlot::route('/{record}/edit'),
        ];
    }
}

