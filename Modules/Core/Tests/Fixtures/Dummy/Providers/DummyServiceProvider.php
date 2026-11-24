<?php

namespace Modules\Core\Tests\Fixtures\Dummy\Providers;

use Modules\Core\Providers\ModuleServiceProvider;

class DummyServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Dummy';
    }
}
