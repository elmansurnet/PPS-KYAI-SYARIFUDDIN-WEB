<?php

use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Filament\Http\Middleware\AuthenticateSession as FilamentAuthenticateSession;
use Illuminate\Support\Facades\Route;
use Modules\Pendaftaran\Http\Controllers\KkDownloadController;
use Modules\Pendaftaran\Http\Controllers\RegistrationWizardController;

/*
 * Wizard pendaftaran publik (server-rendered, tanpa JavaScript). Semua halaman no-store: berisi data
 * pribadi dari draf sesi, tidak boleh disimpan cache peramban/proxy. Semua POST dibatasi RateLimiter
 * bernama (didaftarkan di PendaftaranServiceProvider).
 */
Route::middleware('cache.headers:no_store;private')->prefix('daftar')->group(function () {
    Route::get('/', [RegistrationWizardController::class, 'start'])->name('daftar');
    Route::get('/langkah/{langkah}', [RegistrationWizardController::class, 'show'])
        ->whereIn('langkah', ['1', '2', '3', '4'])->name('daftar.langkah');
    Route::post('/langkah/{langkah}', [RegistrationWizardController::class, 'store'])
        ->whereIn('langkah', ['1', '2', '3'])->middleware('throttle:daftar-langkah')->name('daftar.simpan');
    Route::post('/kirim', [RegistrationWizardController::class, 'submit'])
        ->middleware('throttle:daftar-kirim')->name('daftar.kirim');
    Route::get('/terima-kasih', [RegistrationWizardController::class, 'done'])->name('daftar.selesai');
});

/*
 * Unduh file KK untuk panitia/admin. Di luar route panel Filament tetapi memakai panel "admin" dan
 * middleware Authenticate Filament (tamu diarahkan ke login admin, akun tanpa role 403 lewat
 * canAccessPanel), lalu izin downloadKk (ApplicantPolicy). Hanya id angka (route model binding).
 * AuthenticateSession sama seperti panel: setelah kata sandi diganti, sesi lama juga berakhir di sini.
 */
Route::get('/admin/pendaftar/{applicant}/kk', KkDownloadController::class)
    ->whereNumber('applicant')
    ->middleware(['panel:admin', FilamentAuthenticateSession::class, FilamentAuthenticate::class, 'can:downloadKk,applicant'])
    ->name('pendaftar.kk');
