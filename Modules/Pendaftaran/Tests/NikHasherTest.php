<?php

use Modules\Pendaftaran\Support\NikHasher;

test('hash is hmac sha256 hex of the digits with the configured key', function () {
    config(['pendaftaran.nik_hmac_key' => 'kunci-uji-0123456789-abcdefghijklmnop']);

    expect(NikHasher::hash('3201010101010001'))->toBe(hash_hmac('sha256', '3201010101010001', 'kunci-uji-0123456789-abcdefghijklmnop'));
});

test('hasher throws a clear exception without a key and never falls back to APP_KEY', function (mixed $value) {
    config(['pendaftaran.nik_hmac_key' => $value]);

    expect(fn () => NikHasher::hash('3201010101010001'))
        ->toThrow(RuntimeException::class, 'NIK_HMAC_KEY');
})->with(['', null, '   ', 'secret', 'kunci-31-karakter-0123456789abc']);

test('different keys give different hashes', function () {
    config(['pendaftaran.nik_hmac_key' => 'kunci-satu-0123456789-abcdefghijklmnopq']);
    $one = NikHasher::hash('3201010101010001');
    config(['pendaftaran.nik_hmac_key' => 'kunci-dua-0123456789-abcdefghijklmnopqrs']);

    expect(NikHasher::hash('3201010101010001'))->not->toBe($one);
});

test('input is normalised before hashing', function () {
    expect(NikHasher::hash('3201 0101 0101 0001'))->toBe(NikHasher::hash('3201010101010001'))
        ->and(NikHasher::hash(' 3201-0101-0101-0001 '))->toBe(NikHasher::hash('3201010101010001'));
});
