<?php

use App\Http\Controllers\AccesoDelJugador;
use App\Http\Controllers\PaginasLegales;
use App\Http\Controllers\Portada;
use App\Http\Controllers\VerificarAlta;
use App\Http\Middleware\ExigirEquipo;
use App\Http\Middleware\ExigirJugador;
use App\Http\Middleware\ExigirOrganizador;
use App\Http\Middleware\SoloPlataforma;
use App\Livewire\AltaDeOrganizador;
use App\Livewire\Mesa\AccesoDeMesa;
use App\Livewire\Mesa\MesaDeArbitro;
use App\Livewire\Publico\AccesoJugador;
use App\Livewire\Publico\FichaDelTorneo;
use App\Livewire\Publico\InscripcionEnTorneo;
use App\Livewire\Publico\InvitacionDePareja;
use App\Livewire\Publico\PartidosDePista;
use App\Livewire\Publico\PortalDelJugador;
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
    Route::get('/torneos/{torneo:slug}/inscripcion', InscripcionEnTorneo::class)->name('torneo.inscripcion');

    // El jugador: acceso por enlace, invitación de pareja y su portal.
    Route::get('/jugador/acceso', AccesoJugador::class)->name('jugador.acceso');
    Route::get('/jugador/entrar/{token}', [AccesoDelJugador::class, 'entrar'])->middleware('throttle:20,1')->name('jugador.entrar');
    Route::post('/jugador/salir', [AccesoDelJugador::class, 'salir'])->name('jugador.salir');
    Route::get('/invitacion/{token}', InvitacionDePareja::class)->middleware('throttle:20,1')->name('invitacion');
    Route::get('/jugador', PortalDelJugador::class)->middleware(ExigirJugador::class)->name('jugador.portal');

    // La mesa de árbitros, con la sesión del equipo.
    Route::get('/mesa/acceso', AccesoDeMesa::class)->name('mesa.acceso');
    Route::get('/mesa', MesaDeArbitro::class)->middleware(ExigirEquipo::class)->name('mesa');
});
