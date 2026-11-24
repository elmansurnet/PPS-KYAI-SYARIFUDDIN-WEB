<?php

use Illuminate\Support\Facades\Route;
use Modules\Landing\Http\Controllers\HomeController;

Route::get('/', HomeController::class)->name('home');
