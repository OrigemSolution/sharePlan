<?php

namespace App\Filament\Resources\PasswordSharingSlotResource\Pages;

use App\Filament\Resources\PasswordSharingSlotResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPasswordSharingSlots extends ListRecords
{
    protected static string $resource = PasswordSharingSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
