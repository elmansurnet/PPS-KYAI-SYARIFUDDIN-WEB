<?php

use Illuminate\Support\Facades\Route;
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
