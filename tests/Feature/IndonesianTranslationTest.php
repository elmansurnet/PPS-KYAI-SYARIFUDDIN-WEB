<?php

use Illuminate\Support\Arr;

/*
 * Produksi memakai APP_LOCALE=id. Laravel hanya membawa terjemahan "en", jadi tanpa lang/id formulir
 * admin menampilkan kunci mentah seperti "validation.required".
 */

test('the test environment runs in Indonesian with English as fallback', function () {
    expect(app()->getLocale())->toBe('id')
        ->and(config('app.fallback_locale'))->toBe('en');
});

test('validation messages are translated, not raw keys', function () {
    foreach (['required', 'unique', 'uploaded', 'email', 'max.string', 'min.string', 'mimes'] as $key) {
        $message = __('validation.'.$key, ['attribute' => 'nama', 'max' => 5, 'min' => 2, 'values' => 'pdf']);

        expect($message)->not->toBe('validation.'.$key)->not->toContain('The ')->not->toContain(' field');
    }

    expect(__('validation.required', ['attribute' => 'nama']))->toBe('Nama wajib diisi.');
    expect(validator(['nama' => ''], ['nama' => 'required'])->errors()->first('nama'))->toBe('Nama wajib diisi.');
    expect(validator(['email' => 'x'], ['email' => 'email'])->errors()->first('email'))->toBe('Surel harus berupa alamat surel yang valid.');
});

test('every key of the English validation file exists in the Indonesian file', function () {
    $en = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
    $id = require lang_path('id/validation.php');

    $enKeys = array_keys(Arr::dot(Arr::except($en, ['custom', 'attributes'])));
    $idKeys = array_keys(Arr::dot(Arr::except($id, ['custom', 'attributes'])));

    expect(array_values(array_diff($enKeys, $idKeys)))->toBe([]);
});

test('auth, pagination and password lines exist in Indonesian', function (string $file) {
    $en = require base_path("vendor/laravel/framework/src/Illuminate/Translation/lang/en/{$file}.php");

    foreach (array_keys($en) as $key) {
        expect(__("{$file}.{$key}"))->not->toBe("{$file}.{$key}")->not->toBe($en[$key]);
    }
})->with(['auth', 'pagination', 'passwords']);

test('filament ships Indonesian translations used by the admin panel', function () {
    expect(__('filament-panels::auth/pages/login.form.password.label'))->toBe('Kata sandi');
    expect(__('filament-actions::create.single.label', ['label' => 'Berita']))->toBe('Buat Berita');
});
