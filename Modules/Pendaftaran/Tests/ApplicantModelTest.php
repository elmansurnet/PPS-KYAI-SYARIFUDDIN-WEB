<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Pendaftaran\Models\Applicant;
use Modules\Pendaftaran\Support\NikHasher;

uses(RefreshDatabase::class);

const NIK_A = '3201010101010001';

test('test_nik_is_encrypted_at_rest', function () {
    $applicant = Applicant::factory()->create(['nik' => NIK_A]);

    $raw = DB::table('applicants')->where('id', $applicant->id)->value('nik');

    expect($raw)->not->toBe(NIK_A)
        ->and($raw)->not->toContain(NIK_A)
        ->and($raw)->not->toBe(NikHasher::hash(NIK_A))
        ->and(Applicant::find($applicant->id)->nik)->toBe(NIK_A);
});

test('test_nik_hash_is_set_and_deterministic', function () {
    $a = Applicant::factory()->create(['nik' => NIK_A]);
    $b = Applicant::factory()->make(['nik' => NIK_A]);

    expect($a->nik_hash)->toBe(NikHasher::hash(NIK_A))
        ->and($b->nik_hash)->toBe($a->nik_hash)
        ->and($a->nik_hash)->toMatch('/^[0-9a-f]{64}$/')
        ->and(DB::table('applicants')->where('id', $a->id)->value('nik_hash'))->toBe($a->nik_hash);
});

test('test_duplicate_nik_hash_is_rejected_by_database', function () {
    Applicant::factory()->create(['nik' => NIK_A]);

    expect(fn () => Applicant::factory()->create(['nik' => NIK_A]))->toThrow(QueryException::class);
});

test('test_status_flags_default_to_false', function () {
    $applicant = Applicant::factory()->create()->fresh();

    expect($applicant->sudah_dihubungi)->toBeFalse()
        ->and($applicant->sudah_bayar)->toBeFalse()
        ->and($applicant->sudah_masuk)->toBeFalse();
});

test('changing nik recomputes the hash via setAttribute, update and forceFill', function () {
    $a = Applicant::factory()->create(['nik' => NIK_A]);

    $a->nik = '3201010101010002';
    expect($a->nik_hash)->toBe(NikHasher::hash('3201010101010002'));
    $a->save();

    $a->update(['nik' => '3201010101010003']);
    expect($a->fresh()->nik_hash)->toBe(NikHasher::hash('3201010101010003'))
        ->and($a->fresh()->nik)->toBe('3201010101010003');

    $a->forceFill(['nik' => '3201010101010004'])->save();
    expect($a->fresh()->nik_hash)->toBe(NikHasher::hash('3201010101010004'));
});

test('nik is stored as digits only', function () {
    $a = Applicant::factory()->create(['nik' => '3201 0101 0101 0001']);

    expect($a->fresh()->nik)->toBe(NIK_A)
        ->and($a->nik_hash)->toBe(NikHasher::hash(NIK_A));
});

test('mass assignment cannot set nik_hash, status flags or kk_path', function () {
    $a = new Applicant;
    $a->fill([
        'nik' => NIK_A, 'nama_lengkap' => 'Budi', 'nik_hash' => str_repeat('a', 64),
        'sudah_dihubungi' => true, 'sudah_bayar' => true, 'sudah_masuk' => true, 'kk_path' => 'x.enc',
    ]);

    expect($a->nik_hash)->toBe(NikHasher::hash(NIK_A))
        ->and($a->getAttributes())->not->toHaveKeys(['sudah_dihubungi', 'sudah_bayar', 'sudah_masuk', 'kk_path']);
});

test('status flags can be set explicitly by admin code', function () {
    $a = Applicant::factory()->create();
    $a->forceFill(['sudah_bayar' => true])->save();

    expect($a->fresh()->sudah_bayar)->toBeTrue();
});

test('nik nik_hash and kk_path never leak through toArray or toJson', function () {
    $a = Applicant::factory()->create(['nik' => NIK_A]);
    $a->forceFill(['kk_path' => 'kk/abc.enc'])->save();
    $a = $a->fresh();

    expect($a->toArray())->not->toHaveKeys(['nik', 'nik_hash', 'kk_path'])
        ->and($a->toJson())->not->toContain(NIK_A)->not->toContain($a->nik_hash)->not->toContain('kk/abc.enc');
});

test('findByNik finds through the hash and normalises input', function () {
    $a = Applicant::factory()->create(['nik' => NIK_A]);

    expect(Applicant::findByNik('3201 0101 0101 0001')?->id)->toBe($a->id)
        ->and(Applicant::findByNik('3201010101019999'))->toBeNull();
});

test('nikMasked and nikLast4 helpers', function () {
    $a = Applicant::factory()->create(['nik' => NIK_A]);

    expect($a->nikLast4())->toBe('0001')
        ->and($a->nikMasked())->toBe('************0001');
});

test('casts for dates and booleans', function () {
    $a = Applicant::factory()->create(['tanggal_lahir' => '2010-05-17', 'pernah_mondok' => true])->fresh();

    expect($a->tanggal_lahir->format('Y-m-d'))->toBe('2010-05-17')
        ->and($a->pernah_mondok)->toBeTrue()
        ->and($a->orang_tua_meninggal)->toBeFalse();
});
