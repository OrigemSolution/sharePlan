<?php

namespace App\Filament\Resources\PasswordServiceResource\Pages;

use App\Filament\Resources\PasswordServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPasswordService extends EditRecord
{
    protected static string $resource = PasswordServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
