<?php

use Illuminate\Support\Facades\Route;
use Modules\Berita\Http\Controllers\PostController;

Route::get('/berita', [PostController::class, 'index'])->name('berita.index');
Route::get('/berita/{slug}', [PostController::class, 'show'])->name('berita.show');
