<?php

namespace App\Filament\Resources\SlotResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('member_name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('member_name')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('payment_status', 'paid'))
            ->columns([
                Tables\Columns\TextColumn::make('member_name')
                    ->label('Name'),
                Tables\Columns\TextColumn::make('member_email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('member_phone')
                    ->label('Phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment_id')
                    ->label('Payment ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('d-m-Y'),

            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                    ])
                    ->searchable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
