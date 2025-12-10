<?php

namespace App\Filament\Resources\PasswordSharingSlotResource\Pages;

use App\Filament\Resources\PasswordSharingSlotResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPasswordSharingSlot extends ViewRecord
{
    protected static string $resource = PasswordSharingSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
