<?php

use App\Enums\Ajuste;
use App\Enums\Rol;
use App\Filament\MarcaDelOrganizador;
use App\Filament\Pages\Ajustes;
use App\Models\Organizador;
use App\Models\RegistroDeCambio;
use App\Models\User;
use App\Models\ValorDeAjuste;
use Database\Seeders\PlanesSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    usarOrganizador(Organizador::factory()->create(['id' => 'norte', 'nombre' => 'Pádel Norte']));
    Filament::setCurrentPanel('panel');
});

test('el propietario guarda la marca, el contacto y los textos legales', function () {
    $propietario = User::factory()->rol(Rol::Propietario)->create();
    $this->actingAs($propietario);

    Livewire::test(Ajustes::class)
        ->assertSuccessful()
        ->assertSee('Marca')
        ->fillForm([
            Ajuste::NombrePublico->value => 'Club Pádel Norte',
            Ajuste::ColorPrimario->value => '#0E7490',
            Ajuste::ColorSecundario->value => '',
            Ajuste::ZonaHoraria->value => 'Atlantic/Canary',
            Ajuste::ContactoEmail->value => 'hola@padelnorte.es',
            Ajuste::PrivacidadAliasPorDefecto->value => true,
            Ajuste::TextoLegalAviso->value => 'Titular: Club Pádel Norte.',
        ])
        ->call('guardar')
        ->assertHasNoFormErrors()
        ->assertNotified('Ajustes guardados');

    expect(ValorDeAjuste::obtener(Ajuste::NombrePublico))->toBe('Club Pádel Norte')
        ->and(ValorDeAjuste::obtener(Ajuste::ColorPrimario))->toBe('#0e7490')
        ->and(ValorDeAjuste::obtener(Ajuste::ColorSecundario))->toBeNull()
        ->and(ValorDeAjuste::obtener(Ajuste::ZonaHoraria))->toBe('Atlantic/Canary')
        ->and(ValorDeAjuste::obtener(Ajuste::PrivacidadAliasPorDefecto))->toBeTrue()
        ->and(ValorDeAjuste::obtener(Ajuste::TextoLegalAviso))->toBe('Titular: Club Pádel Norte.')
        ->and(MarcaDelOrganizador::nombre())->toBe('Club Pádel Norte')
        ->and(RegistroDeCambio::query()->where('accion', 'ajustes')->count())->toBe(1);

    // El color tiene que ser un color.
    Livewire::test(Ajustes::class)
        ->fillForm([Ajuste::ColorPrimario->value => 'azul'])
        ->call('guardar')
        ->assertHasFormErrors([Ajuste::ColorPrimario->value]);
});

test('un administrador no entra en los ajustes', function () {
    $this->actingAs(User::factory()->rol(Rol::Administrador)->create());

    Livewire::test(Ajustes::class)->assertForbidden();
});

test('los textos legales del organizador salen en su web, y sin ellos se avisa', function () {
    $this->get('http://norte.saas.test/legal/aviso')->assertOk()->assertSee('todavía no ha publicado este texto');

    comoOrganizador(Organizador::query()->findOrFail('norte'), fn () => ValorDeAjuste::fijar([Ajuste::TextoLegalPrivacidad->value => 'Tratamos los datos para organizar torneos.']));

    $this->get('http://norte.saas.test/legal/privacidad')->assertOk()->assertSee('Tratamos los datos para organizar torneos.');
    $this->get('http://norte.saas.test/alta')->assertNotFound();
});
