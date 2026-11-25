<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

/*
 * Halaman galat berbahasa Indonesia, patuh CSP publik (tanpa <style>, style="...", atau skrip inline),
 * dan tidak bergantung pada database (500/503 harus tetap tampil saat database bermasalah).
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    Route::middleware('web')->get('/__galat/{code}', fn (int $code) => abort($code));
    Route::middleware('web')->get('/__galat-crash', fn () => throw new RuntimeException('rahasia internal'));
});

function expectCspCleanErrorPage(string $html): void
{
    expect($html)
        ->toContain('<html lang="id">')
        ->not->toMatch('~<style\b~i')
        ->not->toMatch('~\sstyle\s*=~i')
        ->not->toMatch('~\son[a-z]+\s*=~i');

    preg_match_all('~<script\b[^>]*>(.*?)</script>~is', $html, $scripts);
    expect(array_filter(array_map('trim', $scripts[1])))->toBe([]);
}

test('unknown news slug shows the Indonesian 404 page', function () {
    $response = $this->get('/berita/tidak-ada-berita-ini')->assertNotFound();

    $response->assertSee('Halaman tidak ditemukan')->assertSee('Ke beranda');
    expectCspCleanErrorPage($response->getContent());
});

test('unmatched routes also use the Indonesian 404 page', function () {
    $response = $this->get('/rute/yang/tidak/ada')->assertNotFound();

    $response->assertSee('Halaman tidak ditemukan');
    expectCspCleanErrorPage($response->getContent());
});

test('419 explains the expired session and links back to the registration form', function () {
    $response = $this->get('/__galat/419')->assertStatus(419);

    $response->assertSee('Sesi Anda sudah berakhir')
        ->assertSee('href="/daftar"', false)
        ->assertSee('Buka lagi formulir pendaftaran');
    expectCspCleanErrorPage($response->getContent());
});

test('429, 403, 503 and other statuses are Indonesian and CSP clean', function (int $code, string $text) {
    $response = $this->get('/__galat/'.$code)->assertStatus($code);

    $response->assertSee($text);
    expectCspCleanErrorPage($response->getContent());
})->with([
    [429, 'Terlalu banyak permintaan'],
    [403, 'Akses ditolak'],
    [503, 'Sedang dalam pemeliharaan'],
    [500, 'Terjadi kesalahan di server'],
    [405, 'Permintaan tidak dapat diproses'],
    [502, 'Terjadi kesalahan di server'],
]);

test('500 page renders without debug details and without the database', function () {
    config(['app.debug' => false]);

    // Pengaturan situs tidak bisa dibaca (mis. database mati): halaman galat tetap tampil dengan nama
    // aplikasi dari config.
    app()->bind(Modules\Core\Settings\SiteSettings::class, fn () => throw new RuntimeException('database mati'));

    $response = $this->get('/__galat-crash')->assertStatus(500);

    $response->assertSee('Terjadi kesalahan di server')
        ->assertSee(config('app.name'))
        ->assertDontSee('rahasia internal')
        ->assertDontSee('RuntimeException');
    expectCspCleanErrorPage($response->getContent());
});
