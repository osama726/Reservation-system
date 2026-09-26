<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('reservation-demo');
});

Route::get('/demo', function () {
    return view('reservation-demo');
});
