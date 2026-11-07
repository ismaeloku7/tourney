<?php

use App\Http\Controllers\AccesoDelJugador;
use App\Http\Controllers\PaginasLegales;
use App\Http\Controllers\Portada;
use App\Http\Controllers\Pwa;
use App\Http\Controllers\VerificarAlta;
use App\Http\Middleware\ExigirEquipo;
use App\Http\Middleware\ExigirJugador;
use App\Http\Middleware\ExigirOrganizador;
use App\Http\Middleware\SoloPlataforma;
use App\Livewire\AltaDeOrganizador;
use App\Livewire\Jugador\Avisos;
use App\Livewire\Jugador\Inicio;
use App\Livewire\Jugador\MisDatos;
use App\Livewire\Jugador\MisPartidos;
use App\Livewire\Jugador\MiTorneo;
use App\Livewire\Mesa\AccesoDeMesa;
use App\Livewire\Mesa\MesaDeArbitro;
use App\Livewire\Publico\AccesoJugador;
use App\Livewire\Publico\FichaDelTorneo;
use App\Livewire\Publico\InscripcionEnTorneo;
use App\Livewire\Publico\InvitacionDePareja;
use App\Livewire\Publico\PartidosDePista;
use App\Livewire\Publico\Tablero;
use App\Models\Inscripcion;
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
    // La aplicación instalable del organizador.
    Route::get('/manifest.webmanifest', [Pwa::class, 'manifiesto'])->name('pwa.manifiesto');
    Route::get('/icono-{tamano}.png', [Pwa::class, 'icono'])->where('tamano', '192|512|maskable-512|apple')->name('pwa.icono');

    Route::get('/torneos/{torneo:slug}', FichaDelTorneo::class)->name('torneo.ficha');
    Route::get('/torneos/{torneo:slug}/tablero', Tablero::class)->name('torneo.tablero');
    Route::get('/torneos/{torneo:slug}/pista/{pista}', PartidosDePista::class)->name('torneo.pista');
    Route::get('/torneos/{torneo:slug}/inscripcion', InscripcionEnTorneo::class)->name('torneo.inscripcion');

    // El jugador: acceso por enlace, invitación de pareja y su portal.
    Route::get('/jugador/acceso', AccesoJugador::class)->name('jugador.acceso');
    Route::get('/jugador/entrar/{token}', [AccesoDelJugador::class, 'entrar'])->middleware('throttle:20,1')->name('jugador.entrar');
    // El enlace de acceso directo de una pareja (lo crea la organización y no caduca).
    Route::get('/acceso/{token}', [AccesoDelJugador::class, 'directo'])->middleware('throttle:20,1')->name('jugador.directo');
    Route::post('/jugador/salir', [AccesoDelJugador::class, 'salir'])->name('jugador.salir');
    Route::get('/invitacion/{token}', InvitacionDePareja::class)->middleware('throttle:20,1')->name('invitacion');
    Route::middleware(ExigirJugador::class)->prefix('jugador')->name('jugador.')->group(function (): void {
        Route::get('/', Inicio::class)->name('portal');
        Route::get('/torneos/{inscripcion}', MiTorneo::class)->name('inscripcion');
        Route::get('/partidos', MisPartidos::class)->name('partidos');
        Route::get('/avisos', Avisos::class)->name('avisos');
        Route::get('/datos', MisDatos::class)->name('datos');
        // La ruta antigua de la inscripción, por los correos ya enviados.
        Route::get('/inscripciones/{inscripcion}', fn (Inscripcion $inscripcion) => redirect()->route('jugador.inscripcion', $inscripcion));
    });

    // La mesa de árbitros, con la sesión del equipo.
    Route::get('/mesa/acceso', AccesoDeMesa::class)->name('mesa.acceso');
    Route::get('/mesa', MesaDeArbitro::class)->middleware(ExigirEquipo::class)->name('mesa');
});
