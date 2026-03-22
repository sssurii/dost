<?php

use App\Providers\AppServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\TelescopeServiceProvider;
use App\Providers\VoltServiceProvider;

return [
    AppServiceProvider::class,
    EventServiceProvider::class,
    TelescopeServiceProvider::class,
    VoltServiceProvider::class,
];
