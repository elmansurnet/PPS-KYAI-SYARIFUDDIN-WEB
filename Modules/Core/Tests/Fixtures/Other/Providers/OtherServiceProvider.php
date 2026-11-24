<?php

namespace Modules\Core\Tests\Fixtures\Other\Providers;

use Modules\Core\Providers\ModuleServiceProvider;

class OtherServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Other';
    }
}
