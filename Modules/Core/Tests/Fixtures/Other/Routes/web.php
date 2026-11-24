<?php

use Illuminate\Support\Facades\Route;

Route::get('/__other', fn () => 'other module');
