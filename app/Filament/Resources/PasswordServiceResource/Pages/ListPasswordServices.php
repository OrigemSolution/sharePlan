<?php

namespace App\Filament\Resources\PasswordServiceResource\Pages;

use App\Filament\Resources\PasswordServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPasswordServices extends ListRecords
{
    protected static string $resource = PasswordServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
