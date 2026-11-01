<?php

use App\Http\Controllers\Portada;
use Illuminate\Support\Facades\Route;

// Landing en el dominio del SaaS; portada del organizador en su host.
Route::get('/', Portada::class)->name('home');
