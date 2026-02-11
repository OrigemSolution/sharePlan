<?php

namespace App\Filament\Resources\PasswordServiceResource\Pages;

use App\Filament\Resources\PasswordServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePasswordService extends CreateRecord
{
    protected static string $resource = PasswordServiceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
