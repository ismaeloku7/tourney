<?php

use App\Enums\EstadoTorneo;
use App\Enums\Rol;
use App\Filament\ProximoTorneo;
use App\Filament\Resources\Torneos\TorneoResource;
use App\Models\Organizador;
use App\Models\Torneo;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

/** El primer botón del menú lleva directo al torneo que toca. */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create());
    Filament::setCurrentPanel('panel');
});

/** El primer elemento del menú, calculado de nuevo (como en una petición nueva). */
function primeroDelMenu(): ?NavigationItem
{
    request()->attributes->remove('proximo_torneo');
    $grupo = collect(Filament::getNavigation())->first();

    return $grupo === null ? null : collect($grupo->getItems())->first();
}

/** @return array<string, string|null> Las páginas del submenú, por nombre. */
function paginasDelMenu(?NavigationItem $item): array
{
    return collect($item?->getChildItems() ?? [])
        ->mapWithKeys(fn (NavigationItem $hijo): array => [(string) $hijo->getLabel() => $hijo->getUrl()])
        ->all();
}

function torneoDePrueba(string $nombre, EstadoTorneo $estado, int $empiezaEnDias): Torneo
{
    return Torneo::factory()->create([
        'nombre' => $nombre,
        'estado' => $estado,
        'fecha_inicio' => today()->addDays($empiezaEnDias)->toDateString(),
        'fecha_fin' => today()->addDays($empiezaEnDias + 1)->toDateString(),
    ]);
}

test('el primero del menú es el torneo en juego o, si no hay, el siguiente que empieza', function () {
    $this->actingAs(User::factory()->rol(Rol::Propietario)->create());
    torneoDePrueba('Ya jugado', EstadoTorneo::Finalizado, -10);
    torneoDePrueba('Pasado sin cerrar', EstadoTorneo::InscripcionCerrada, -10);
    torneoDePrueba('Lejano', EstadoTorneo::InscripcionAbierta, 40);
    $cercano = torneoDePrueba('Rally', EstadoTorneo::Borrador, 5);

    $primero = primeroDelMenu();
    expect($primero?->getLabel())->toBe('Rally')
        ->and($primero?->getBadge())->toBe('Borrador')
        ->and(paginasDelMenu($primero))->toBe([
            'Ficha' => TorneoResource::getUrl('view', ['record' => $cercano]),
            'Competición' => TorneoResource::getUrl('competicion', ['record' => $cercano]),
            'Partidos' => TorneoResource::getUrl('partidos', ['record' => $cercano]),
            'Horarios' => TorneoResource::getUrl('horarios', ['record' => $cercano]),
        ]);

    // Uno en juego va por delante, aunque haya empezado antes.
    $enJuego = torneoDePrueba('En marcha', EstadoTorneo::EnJuego, -3);
    expect(paginasDelMenu(primeroDelMenu())['Horarios'] ?? null)->toBe(TorneoResource::getUrl('horarios', ['record' => $enJuego]));
});

test('un administrador ve el próximo de los suyos y, sin torneos, no hay acceso directo', function () {
    $administrador = User::factory()->rol(Rol::Administrador)->create();
    $this->actingAs($administrador);
    torneoDePrueba('De otros', EstadoTorneo::InscripcionAbierta, 2);

    expect(ProximoTorneo::torneo())->toBeNull()
        ->and(primeroDelMenu()?->getLabel())->not->toBe('De otros');

    $suyo = torneoDePrueba('El suyo', EstadoTorneo::InscripcionAbierta, 20);
    $suyo->administradores()->attach($administrador);

    expect(primeroDelMenu()?->getLabel())->toBe('El suyo');
});
