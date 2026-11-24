<?php

use Illuminate\Support\Facades\Route;

Route::get('/__dummy', fn () => view('dummy::hello'));
