<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use App\Models\SocialMedia;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Tables\Filters\SelectFilter;
use Filament\Infolists;


class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role_id', 1);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(20),
                        Forms\Components\TextInput::make('whatsapp_phone')
                            ->tel()
                            ->required()
                            ->maxLength(20),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required()
                            ->minLength(8)
                            ->maxLength(255)
                            ->dehydrateStateUsing(fn ($state) => bcrypt($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->hiddenOn('edit'),
                    ])->columns(2),
                
                Forms\Components\Section::make('Bank Details')
                    ->schema([
                        Forms\Components\TextInput::make('bank')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('account_no')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('account_name')
                            ->maxLength(255),
                    ])->columns(2),
                
                Forms\Components\Section::make('Social Media Handles')
                    ->schema([
                        Forms\Components\Repeater::make('socialMedia')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('name')
                                    ->options([
                                        'facebook' => 'Facebook',
                                        'twitter' => 'Twitter',
                                        'instagram' => 'Instagram',
                                        'linkedin' => 'LinkedIn',
                                        'tiktok' => 'TikTok',
                                        'youtube' => 'YouTube',
                                    ])
                                    ->required(),
                                Forms\Components\TextInput::make('handle')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->defaultItems(3) // Default to 3 social media handles
                            ->minItems(3) // Require exactly 3
                            // ->maxItems(3)
                            ->columnSpanFull(),
                    ]),
                
                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'verified' => 'Verify',
                                'rejected' => 'Rejected',
                            ])
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('WhatsApp No.')
                    ->label('WhatsApp No.')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'verified' => 'info',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'verified' => 'Verified',
                        'rejected' => 'Rejected',
                    ]),
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
                Infolists\Components\Section::make('User Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('email'),
                        Infolists\Components\TextEntry::make('phone'),
                        Infolists\Components\TextEntry::make('whatsapp_phone')
                            ->label('WhatsApp Phone'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'verified' => 'success',
                                'rejected' => 'danger',
                            }),
                    ])->columns(2),
                
                Infolists\Components\Section::make('Bank Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('bank'),
                        Infolists\Components\TextEntry::make('account_no')
                            ->label('Account Number'),
                        Infolists\Components\TextEntry::make('account_name')
                            ->label('Account Name'),
                    ])->columns(2),
                
                Infolists\Components\Section::make('Social Media Handles')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('socialMedia')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->badge(),
                                Infolists\Components\TextEntry::make('handle')
                                    ->url(fn (SocialMedia $record): string => match ($record->name) {
                                        'facebook' => "https://facebook.com/{$record->handle}",
                                        'twitter' => "https://twitter.com/{$record->handle}",
                                        'instagram' => "https://instagram.com/{$record->handle}",
                                        'linkedin' => "https://linkedin.com/in/{$record->handle}",
                                        'tiktok' => "https://tiktok.com/@{$record->handle}",
                                        'youtube' => "https://youtube.com/@{$record->handle}",
                                        default => null,
                                    })
                                    ->openUrlInNewTab(),
                            ])
                            ->columns(2),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
