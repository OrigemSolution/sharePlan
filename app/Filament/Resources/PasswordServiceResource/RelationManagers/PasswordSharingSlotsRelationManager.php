<?php

namespace App\Filament\Resources\PasswordServiceResource\RelationManagers;

use App\Filament\Resources\PasswordSharingSlotResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class PasswordSharingSlotsRelationManager extends RelationManager
{
    protected static string $relationship = 'passwordSharingSlots';

    public function form(Form $form): Form
    {
        // Creation/edition of slots is handled via API/payment flow,
        // so we don't expose a create/edit form here.
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Creator')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('guest_limit')
                    ->label('Required Guests')
                    ->sortable(),
                Tables\Columns\TextColumn::make('current_members')
                    ->label('Current Guests')
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration')
                    ->label('Duration')
                    ->suffix(' month(s)')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment Status')
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Payment Status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                    ]),
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Active')
                    ->options([
                        true => 'Active',
                        false => 'Inactive',
                    ]),
            ])
            ->headerActions([
                // No create here; slots are created via the user-facing flow.
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                ->url(fn ($record) => PasswordSharingSlotResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([
                // Read-only list in this context.
            ]);
    }
}

