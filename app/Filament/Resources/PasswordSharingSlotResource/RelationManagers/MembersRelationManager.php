<?php

namespace App\Filament\Resources\PasswordSharingSlotResource\RelationManagers;

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
            ->columns([
                Tables\Columns\TextColumn::make('member_name'),
                Tables\Columns\TextColumn::make('member_email')->label('Email')->searchable(),
                Tables\Columns\TextColumn::make('member_phone')->label('Phone')->searchable(),
                Tables\Columns\TextColumn::make('payment_status')->label('Payment Status'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_status')->options([
                    'pending' => 'Pending',
                    'paid' => 'Paid',
                ])
            ])
            ->headerActions([
                // disable creation from admin
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }
}