<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SlotResource\Pages;
use App\Filament\Resources\SlotResource\RelationManagers\MembersRelationManager;
use App\Models\Slot;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Database\Eloquent\Model;


class SlotResource extends Resource
{
    protected static ?string $model = Slot::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Core';
    

    protected static ?int $navigationSort = 1;
    
    public static function canCreate(): bool
    {
        // Prevent creating new transactions
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('service_id')
                    ->relationship('service', 'name')
                    ->required(),
                Forms\Components\TextInput::make('duration')
                    ->required(),
                Forms\Components\TextInput::make('current_members')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'open' => 'Open',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->required(),
                Forms\Components\Select::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
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
                Tables\Columns\TextColumn::make('service.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration')
                    ->suffix(' month(s)')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                    Tables\Columns\TextColumn::make('pending_members_count')
                    ->label('Pending Members')
                    ->getStateUsing(function (Model $record): string {
                        return $record->members()->where('payment_status', 'pending')->count();
                    })
                    ->searchable(false),
                Tables\Columns\TextColumn::make('paid_members_count')
                    ->label('Paid Members')
                    ->getStateUsing(function (Model $record): string {
                        return $record->members()->where('payment_status', 'paid')->count();
                    })
                    ->searchable(false),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Status')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->since()
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('service_id')
                    ->relationship('service', 'name')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->searchable(),
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
                TextEntry::make('service.name')
                    ->label('Service'),
                TextEntry::make('creator.name')
                    ->label('Creator'),
                TextEntry::make('duration')
                    ->label('Duration')
                    ->suffix(' month(s)'),
                TextEntry::make('paid_members_count')
                    ->label('Paid Members')
                    ->getStateUsing(function (Model $record): string {
                        return $record->members()->where('payment_status', 'paid')->count();
                    }),
                TextEntry::make('status')
                    ->label('Status'),
                TextEntry::make('payment_status')
                    ->label('Payment Status'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MembersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSlots::route('/'),
            'view' => Pages\ViewSlot::route('/{record}'),
            'edit' => Pages\EditSlot::route('/{record}/edit'),
        ];
    }
}
