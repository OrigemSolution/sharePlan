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
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
