<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Filament\Resources\PaymentResource\RelationManagers;
use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationGroup = 'Others';

    public static function canCreate(): bool
    {
        // Prevent creating new transactions
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        // Prevent editing transactions
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
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payment_type')
                    ->label('Payment Type')
                    ->getStateUsing(function ($record) {
                        if ($record->password_sharing_slot_id) {
                            return 'Password Sharing';
                        }
                        return 'Regular Slot';
                    })
                    ->badge()
                    ->color(fn ($state) => $state === 'Password Sharing' ? 'success' : 'primary'),
                 Tables\Columns\TextColumn::make('service_name')
                    ->label('Service')
                    ->getStateUsing(function ($record) {
                        if ($record->password_sharing_slot_id && $record->passwordSharingSlot) {
                            return $record->passwordSharingSlot->passwordService->name ?? 'N/A';
                        }
                        if ($record->slot && $record->slot->service) {
                            return $record->slot->service->name ?? 'N/A';
                        }
                        return 'N/A';
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($query) use ($search) {
                            $query->whereHas('slot.service', function ($q) use ($search) {
                                $q->where('name', 'like', "%{$search}%");
                            })
                            ->orWhereHas('passwordSharingSlot.passwordService', function ($q) use ($search) {
                                $q->where('name', 'like', "%{$search}%");
                            });
                        });
                    }),
                Tables\Columns\TextColumn::make('slot_id')
                    ->label('Slot ID')
                    ->searchable()
                    ->getStateUsing(function ($record) {
                        if ($record->password_sharing_slot_id) {
                            return 'PS-' . $record->password_sharing_slot_id;
                        }
                        return $record->slot_id ? 'S-' . $record->slot_id : 'N/A';
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Creator')
                    ->searchable(),
                Tables\Columns\TextColumn::make('payer_email')
                    ->label('Payer Email')
                    ->getStateUsing(function ($record) {
                        if ($record->passwordSharingSlotMember) {
                            return $record->passwordSharingSlotMember->member_email ?? 'N/A';
                        }
                        if ($record->slotMember) {
                            return $record->slotMember->member_email ?? 'N/A';
                        }
                        return 'N/A';
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($query) use ($search) {
                            $query->whereHas('passwordSharingSlotMember', function ($q) use ($search) {
                                $q->where('member_email', 'like', "%{$search}%");
                            })
                            ->orWhereHas('slotMember', function ($q) use ($search) {
                                $q->where('member_email', 'like', "%{$search}%");
                            });
                        });
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount (₦)')
                    ->formatStateUsing(function ($state) {
                        $amount = $state / 100;
                        return '₦' . number_format($amount, 2);
                    }),
                Tables\Columns\TextColumn::make('reference')
                    ->label('Payment Reference')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'success' => 'success',
                        'pending' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->label('Status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment_channel')
                    ->label('Channel')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->searchable(),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                return $query->with([
                    'slot.service',
                    'slot.creator',
                    'slotMember',
                    'passwordSharingSlot.passwordService',
                    'passwordSharingSlot.user',
                    'passwordSharingSlotMember',
                ]);
            })
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('payment_type')
                    ->label('Payment Type')
                    ->options([
                        'regular' => 'Regular Slot',
                        'password_sharing' => 'Password Sharing',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === 'regular') {
                            return $query->whereNotNull('slot_id')->whereNull('password_sharing_slot_id');
                        }
                        if ($data['value'] === 'password_sharing') {
                            return $query->whereNotNull('password_sharing_slot_id');
                        }
                        return $query;
                    }),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->searchable(),
                Tables\Filters\SelectFilter::make('payment_channel')
                    ->label('Channel')
                    ->searchable(),
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //
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
            'index' => Pages\ListPayments::route('/'),
            // 'create' => Pages\CreatePayment::route('/create'),
            //'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }
}
