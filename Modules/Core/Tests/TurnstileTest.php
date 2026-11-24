<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\Core\Rules\TurnstileRule;
use Modules\Core\Services\TurnstileVerifier;

const TURNSTILE_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

beforeEach(function () {
    config(['services.turnstile.secret' => 'secret-123']);
});

test('test_turnstile_accepts_valid_token', function () {
    Http::fake([TURNSTILE_URL => Http::response(['success' => true])]);

    expect(app(TurnstileVerifier::class)->passes('tok', '1.2.3.4'))->toBeTrue();

    Http::assertSent(fn ($r) => $r->url() === TURNSTILE_URL
        && $r['secret'] === 'secret-123'
        && $r['response'] === 'tok'
        && $r['remoteip'] === '1.2.3.4');
});

test('remoteip is omitted when no ip is given', function () {
    Http::fake([TURNSTILE_URL => Http::response(['success' => true])]);

    app(TurnstileVerifier::class)->passes('tok');

    Http::assertSent(fn ($r) => ! isset($r['remoteip']));
});

test('test_turnstile_rejects_token_when_cloudflare_says_fail', function () {
    Http::fake([TURNSTILE_URL => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

    expect(app(TurnstileVerifier::class)->passes('bad'))->toBeFalse();
});

test('success must be strictly true', function () {
    Http::fake([TURNSTILE_URL => Http::response(['success' => 'true'])]);

    expect(app(TurnstileVerifier::class)->passes('tok'))->toBeFalse();
});

test('empty token returns false without an http call', function () {
    Http::fake();

    expect(app(TurnstileVerifier::class)->passes(''))->toBeFalse();

    Http::assertNothingSent();
});

test('network failure fails closed and logs a warning', function () {
    Log::spy();
    Http::fake([TURNSTILE_URL => fn () => throw new ConnectionException('timeout')]);

    expect(app(TurnstileVerifier::class)->passes('tok'))->toBeFalse();

    Log::shouldHaveReceived('warning')->once();
});

test('http error status fails closed', function () {
    Http::fake([TURNSTILE_URL => Http::response('boom', 500)]);

    expect(app(TurnstileVerifier::class)->passes('tok'))->toBeFalse();
});

test('malformed json fails closed', function () {
    Http::fake([TURNSTILE_URL => Http::response('<html>not json</html>', 200)]);

    expect(app(TurnstileVerifier::class)->passes('tok'))->toBeFalse();
});

test('missing secret fails closed without an http call', function () {
    config(['services.turnstile.secret' => '']);
    Http::fake();

    expect(app(TurnstileVerifier::class)->passes('tok'))->toBeFalse();

    Http::assertNothingSent();
});

test('rule passes on a valid token', function () {
    Http::fake([TURNSTILE_URL => Http::response(['success' => true])]);

    $v = Validator::make(['cf-turnstile-response' => 'tok'], ['cf-turnstile-response' => [new TurnstileRule]]);

    expect($v->passes())->toBeTrue();
});

test('rule fails with an Indonesian message', function () {
    Http::fake([TURNSTILE_URL => Http::response(['success' => false])]);

    $v = Validator::make(['cf-turnstile-response' => 'tok'], ['cf-turnstile-response' => [new TurnstileRule]]);

    expect($v->fails())->toBeTrue();
    expect($v->errors()->first('cf-turnstile-response'))->toContain('Verifikasi keamanan gagal');
});

test('rule fails on a missing field without an http call', function () {
    Http::fake();

    $v = Validator::make([], ['cf-turnstile-response' => [new TurnstileRule]]);

    expect($v->fails())->toBeTrue();
    Http::assertNothingSent();
});

test('config exposes turnstile keys, .env.example has placeholders, verifier has no hardcoded bypass', function () {
    expect(config('services.turnstile'))->toHaveKeys(['site_key', 'secret']);
    expect(file_get_contents(base_path('.env.example')))
        ->toContain('TURNSTILE_SITE_KEY=')->toContain('TURNSTILE_SECRET_KEY=');
    expect(file_get_contents(base_path('Modules/Core/Services/TurnstileVerifier.php')))
        ->not->toContain('1x0000000000000000000000000000000AA');
});

test('empty secret logs a warning naming the key but never a value', function () {
    config(['services.turnstile.secret' => '']);
    Log::spy();

    expect(app(TurnstileVerifier::class)->passes('tok'))->toBeFalse();

    Log::shouldHaveReceived('warning')->withArgs(fn ($m) => str_contains($m, 'TURNSTILE_SECRET_KEY'))->once();
});

test('always-pass test keys are used only when APP_ENV is exactly testing', function () {
    $run = function (string $env): array {
        $code = 'function env($k, $d = null) { $v = getenv($k); return $v === false ? $d : $v; } echo json_encode(require "config/services.php");';
        $result = Illuminate\Support\Facades\Process::path(base_path())
            ->env(['APP_ENV' => $env, 'TURNSTILE_SECRET_KEY' => '', 'TURNSTILE_SITE_KEY' => ''])
            ->run([PHP_BINARY, '-r', $code]);

        return json_decode($result->output(), true)['turnstile'];
    };

    expect($run('testing')['secret'])->not->toBeNull();
    expect($run('local')['secret'])->toBeNull();
    expect($run('production')['secret'])->toBeNull();
});
