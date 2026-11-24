<?php

use Modules\Core\Contracts\LatestNewsProvider;
use Modules\Core\Providers\CoreServiceProvider;
use Modules\Core\Tests\Fixtures\Dummy\Providers\DummyServiceProvider;
use Modules\Core\Tests\Fixtures\Other\Providers\OtherServiceProvider;

function enableModules(array $providers): void
{
    config(['modules.enabled' => $providers]);
    app()->getProvider(CoreServiceProvider::class)->registerEnabledModules();
}

test('test_enabled_module_loads_its_views_and_routes', function () {
    enableModules([DummyServiceProvider::class]);

    $this->get('/__dummy')->assertOk()->assertSee('dummy module view');
});

test('test_module_not_in_config_is_not_loaded', function () {
    enableModules([OtherServiceProvider::class]);

    $this->get('/__dummy')->assertNotFound();
    expect(view()->exists('dummy::hello'))->toBeFalse();
});

test('test_disabling_a_module_does_not_break_others', function () {
    enableModules([OtherServiceProvider::class]);

    $this->get('/__dummy')->assertNotFound();
    $this->get('/__other')->assertOk();
    $this->get('/')->assertOk();
});

test('latest news provider is bound to an empty implementation', function () {
    expect(app(LatestNewsProvider::class)->latest(3))->toBeEmpty();
});

test('registered filament plugins is empty when modules declare none', function () {
    enableModules([DummyServiceProvider::class]);

    expect(app()->getProvider(CoreServiceProvider::class)->registeredFilamentPlugins())->toBe([]);
});
