<?php

use App\Http\Controllers\PaginasLegales;
use App\Http\Controllers\Portada;
use App\Http\Controllers\VerificarAlta;
use App\Http\Middleware\ExigirOrganizador;
use App\Http\Middleware\SoloPlataforma;
use App\Livewire\AltaDeOrganizador;
use App\Livewire\Publico\FichaDelTorneo;
use App\Livewire\Publico\PartidosDePista;
use App\Livewire\Publico\Tablero;
use Illuminate\Support\Facades\Route;

// Landing en el dominio del SaaS; portada del organizador en su host.
Route::get('/', Portada::class)->name('home');

// Legales: los del titular del SaaS en su dominio, los del organizador en el suyo.
Route::get('/legal/aviso', [PaginasLegales::class, 'aviso'])->name('legal.aviso');
Route::get('/legal/privacidad', [PaginasLegales::class, 'privacidad'])->name('legal.privacidad');

// El alta self-service solo existe en el dominio de la plataforma.
Route::middleware(SoloPlataforma::class)->group(function (): void {
    Route::get('/alta', AltaDeOrganizador::class)->name('alta');
    Route::get('/alta/verificar/{organizador}/{token}', VerificarAlta::class)->name('alta.verificar');
});

// Las páginas públicas de un torneo, en el host de su organizador.
Route::middleware(ExigirOrganizador::class)->group(function (): void {
    Route::get('/torneos/{torneo:slug}', FichaDelTorneo::class)->name('torneo.ficha');
    Route::get('/torneos/{torneo:slug}/tablero', Tablero::class)->name('torneo.tablero');
    Route::get('/torneos/{torneo:slug}/pista/{pista}', PartidosDePista::class)->name('torneo.pista');
});
