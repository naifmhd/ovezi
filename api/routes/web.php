<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::view('/privacy', 'privacy')->name('privacy');
Route::view('/terms', 'terms')->name('terms');
Route::view('/support', 'support')->name('support');
