<?php

use Modules\Pendaftaran\Support\NikHasher;

test('hash is hmac sha256 hex of the digits with the configured key', function () {
    config(['pendaftaran.nik_hmac_key' => 'kunci-uji']);

    expect(NikHasher::hash('3201010101010001'))->toBe(hash_hmac('sha256', '3201010101010001', 'kunci-uji'));
});

test('hasher throws a clear exception without a key and never falls back to APP_KEY', function (mixed $value) {
    config(['pendaftaran.nik_hmac_key' => $value]);

    expect(fn () => NikHasher::hash('3201010101010001'))
        ->toThrow(RuntimeException::class, 'NIK_HMAC_KEY');
})->with(['', null, '   ']);

test('different keys give different hashes', function () {
    config(['pendaftaran.nik_hmac_key' => 'kunci-satu']);
    $one = NikHasher::hash('3201010101010001');
    config(['pendaftaran.nik_hmac_key' => 'kunci-dua']);

    expect(NikHasher::hash('3201010101010001'))->not->toBe($one);
});

test('input is normalised before hashing', function () {
    expect(NikHasher::hash('3201 0101 0101 0001'))->toBe(NikHasher::hash('3201010101010001'))
        ->and(NikHasher::hash(' 3201-0101-0101-0001 '))->toBe(NikHasher::hash('3201010101010001'));
});
