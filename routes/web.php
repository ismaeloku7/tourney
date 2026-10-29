<?php

use Illuminate\Support\Facades\Route;

// Landing en el dominio del SaaS; portada del organizador en su host (fase 2).
Route::view('/', 'portada')->name('home');
