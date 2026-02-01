<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::livewire('users', 'pages::users.index')->name('users');
Route::livewire('schools', 'pages::schools.index')->name('schools.index');

require __DIR__.'/settings.php';
