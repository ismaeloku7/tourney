<?php

use App\Copias\CopiaDeSeguridad;
use App\Enums\Rol;
use App\Filament\Pages\CopiasDeSeguridad;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** Las copias de seguridad: desde el panel, por comando y cada noche. */
beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    Storage::fake('local');
    $this->norte = Organizador::factory()->create(['id' => 'norte', 'nombre' => 'Pádel Norte']);
    usarOrganizador($this->norte);
    Filament::setCurrentPanel('panel');

    Jugador::factory()->create(['nombre' => 'Ana', 'apellidos' => 'Zubiri']);
    Storage::disk('local')->put('organizadores/norte/logo.png', 'png-falso');
});

/** @return list<string> */
function contenidoDelZip(string $archivo): array
{
    $zip = new ZipArchive;
    expect($zip->open(Storage::disk('local')->path($archivo)))->toBeTrue();
    $nombres = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $nombres[] = (string) $zip->getNameIndex($i);
    }
    $sql = (string) $zip->getFromName('base.sql');
    $zip->close();

    return [$nombres, $sql];
}

test('la copia de un organizador lleva su base volcada y sus archivos, y la central su base', function () {
    $ruta = app(CopiaDeSeguridad::class)->deOrganizador($this->norte);

    expect($ruta)->toStartWith('copias/norte/copia-')->toEndWith('.zip');
    [$nombres, $sql] = contenidoDelZip($ruta);
    expect($nombres)->toContain('base.sql')->toContain('archivos/logo.png')
        ->and($sql)->toContain('CREATE TABLE')->toContain('jugadores')->toContain('Zubiri');

    $central = app(CopiaDeSeguridad::class)->deLaCentral();
    [$nombres, $sql] = contenidoDelZip($central);
    expect($central)->toStartWith('copias/central/')
        ->and($nombres)->toContain('base.sql')
        ->and($sql)->toContain('organizadores')->toContain('Pádel Norte');
});

test('el comando copia la central y cada organizador, y solo se conservan las últimas treinta', function () {
    foreach (range(1, 31) as $i) {
        Storage::disk('local')->put(sprintf('copias/norte/copia-2026-01-%02d-000000.zip', $i), 'vieja');
    }

    $this->artisan('copias:hacer')->expectsOutputToContain('Copias hechas: 2. Fallos: 0.')->assertSuccessful();

    $copias = CopiaDeSeguridad::listar($this->norte);
    expect($copias)->toHaveCount(CopiaDeSeguridad::CONSERVAR)
        ->and($copias[0]['nombre'])->toStartWith('copia-'.now()->format('Y-m-d'))
        ->and(Storage::disk('local')->exists('copias/norte/copia-2026-01-01-000000.zip'))->toBeFalse()
        ->and(Storage::disk('local')->exists('copias/norte/copia-2026-01-31-000000.zip'))->toBeTrue()
        ->and(CopiaDeSeguridad::listar(null))->toHaveCount(1);

    $this->artisan('copias:hacer', ['--organizador' => 'norte'])->expectsOutputToContain('Copias hechas: 1.')->assertSuccessful();
});

test('el propietario hace y descarga copias desde el panel; un administrador no entra', function () {
    $this->actingAs(User::factory()->rol(Rol::Propietario)->create());

    Livewire::test(CopiasDeSeguridad::class)
        ->assertSuccessful()
        ->assertSee('Todavía no hay ninguna copia')
        ->callAction('hacer')
        ->assertNotified('Copia hecha')
        ->assertSee('copia-'.now()->format('Y-m-d'));

    $copia = CopiasDeSeguridad::class;
    $nombre = CopiaDeSeguridad::listar($this->norte)[0]['nombre'];
    Livewire::test($copia)->callAction('descargar', arguments: ['archivo' => $nombre])->assertFileDownloaded($nombre);
    Livewire::test($copia)->callAction('descargar', arguments: ['archivo' => 'no-existe.zip'])->assertNotified('Esa copia ya no está');

    $this->actingAs(User::factory()->rol(Rol::Administrador)->create());
    expect(CopiasDeSeguridad::canAccess())->toBeFalse();
});
