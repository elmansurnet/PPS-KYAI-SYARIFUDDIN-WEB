<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Pendaftaran\Models\Applicant;
use Modules\Pendaftaran\Support\NikHasher;

uses(RefreshDatabase::class);

test('rehash command recomputes hashes after the key changes and is idempotent', function () {
    $a = Applicant::factory()->create(['nik' => '3201010101010001']);
    $b = Applicant::factory()->create(['nik' => '3201010101010002']);
    $oldHash = $a->nik_hash;

    config(['pendaftaran.nik_hmac_key' => 'kunci-baru-yang-panjang-sekali-0123456789']);
    expect(Applicant::findByNik('3201010101010001'))->toBeNull();

    $this->artisan('pendaftaran:rehash-nik')->assertSuccessful();

    expect(Applicant::findByNik('3201010101010001')?->id)->toBe($a->id)
        ->and(Applicant::findByNik('3201010101010002')?->id)->toBe($b->id)
        ->and($a->fresh()->nik_hash)->not->toBe($oldHash)
        ->and($a->fresh()->nik_hash)->toBe(NikHasher::hash('3201010101010001'));

    $snapshot = DB::table('applicants')->orderBy('id')->pluck('nik_hash')->all();
    $this->artisan('pendaftaran:rehash-nik')->assertSuccessful();
    expect(DB::table('applicants')->orderBy('id')->pluck('nik_hash')->all())->toBe($snapshot);
});

test('rehash command output never contains a nik', function () {
    Applicant::factory()->create(['nik' => '3201010101010001']);

    $this->artisan('pendaftaran:rehash-nik')
        ->doesntExpectOutputToContain('3201010101010001')
        ->assertSuccessful();
});

test('rehash command fails without a key', function () {
    config(['pendaftaran.nik_hmac_key' => '']);

    $this->artisan('pendaftaran:rehash-nik')->assertFailed();
});
