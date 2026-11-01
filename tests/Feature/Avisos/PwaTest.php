<?php

use App\Enums\Ajuste;
use App\Models\Organizador;
use App\Models\ValorDeAjuste;
use Database\Seeders\PlanesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    $this->organizador = Organizador::factory()->create(['id' => 'norte', 'nombre' => 'Pádel Norte']);
    comoOrganizador($this->organizador, fn () => ValorDeAjuste::fijar([Ajuste::ColorPrimario->value => '#0e7490']));
});

test('cada organizador sirve su manifiesto, con su nombre y su color, que abre en su portada', function () {
    $this->get('http://norte.saas.test/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json; charset=utf-8')
        ->assertJsonPath('name', 'Pádel Norte')
        ->assertJsonPath('theme_color', '#0e7490')
        ->assertJsonPath('start_url', '/?origen=app')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonCount(3, 'icons');

    $this->get('http://saas.test/manifest.webmanifest')->assertNotFound();

    // La web pública enlaza el manifiesto y registra el service worker.
    $this->get('http://norte.saas.test/')->assertSee('/manifest.webmanifest', false)->assertSee("register('/sw.js'", false);
    expect(file_exists(public_path('sw.js')))->toBeTrue()
        ->and(file_exists(public_path('sin-conexion.html')))->toBeTrue();
});

test('sin logotipo salen los iconos de partida; con logotipo, sacados de él', function () {
    $partida = $this->get('http://norte.saas.test/icono-192.png')->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($partida->baseResponse)->toBeInstanceOf(BinaryFileResponse::class);

    Storage::fake('public');
    $logo = UploadedFile::fake()->image('logo.png', 300, 200)->store('organizadores/norte', 'public');
    comoOrganizador($this->organizador, fn () => ValorDeAjuste::fijar([Ajuste::LogoPath->value => $logo]));

    $respuesta = $this->get('http://norte.saas.test/icono-maskable-512.png')->assertOk();
    $tamano = getimagesizefromstring((string) $respuesta->getContent());

    expect($tamano[0])->toBe(512)->and($tamano[1])->toBe(512)
        ->and(Storage::disk('public')->files('organizadores/norte/iconos'))->toHaveCount(1);

    $this->get('http://norte.saas.test/icono-999.png')->assertNotFound();
});
