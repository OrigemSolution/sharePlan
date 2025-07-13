<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\User;
use App\Models\Service;
use App\Models\Slot;
use Filament\Support\Enums\IconPosition;
use Illuminate\Support\Number; 

class DashboardWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Users', Number::format(User::where('role_id', '1')->count()))
                ->description('Share Plan Users')
                ->descriptionIcon('heroicon-m-users', IconPosition::Before)
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('primary'),
            
            Stat::make('Services', Number::format(Service::count()))
                ->description('Share Plan Services')
                ->descriptionIcon('heroicon-m-briefcase', IconPosition::Before)
                ->chart([7, 2, 15, 4, 17])
                ->color('success'),
                
            Stat::make('Slots', Number::format(Slot::count()))
                ->description('Share Plan Slots')
                ->descriptionIcon('heroicon-m-clipboard-document-list', IconPosition::Before)
                ->chart([7, 2, 15, 4, 17])
                ->color('info'),
        ];
    }
}
