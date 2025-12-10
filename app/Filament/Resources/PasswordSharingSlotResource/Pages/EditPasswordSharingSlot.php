<?php

namespace App\Filament\Resources\PasswordSharingSlotResource\Pages;

use App\Filament\Resources\PasswordSharingSlotResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPasswordSharingSlot extends EditRecord
{
    protected static string $resource = PasswordSharingSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
