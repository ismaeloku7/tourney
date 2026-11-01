<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\PanelDelOrganizadorProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    PanelDelOrganizadorProvider::class,
];
