<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use Modules\Core\Providers\CoreServiceProvider;

return [
    AppServiceProvider::class,
    CoreServiceProvider::class,
    AdminPanelProvider::class,
];
