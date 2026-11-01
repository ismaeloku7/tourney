<?php

use App\Actions\Inscripciones\CompletarPareja;
use App\Actions\Inscripciones\ConfirmarParejasCompletas;
use App\Actions\Inscripciones\ImportarParejas;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoInscripcion;
use App\Enums\OrigenInscripcion;
use App\Enums\Rol;
use App\Filament\Resources\Categorias\Pages\EditCategoria;
use App\Filament\Resources\Categorias\RelationManagers\InscripcionesRelationManager;
use App\Filament\Resources\Torneos\Pages\EditTorneo;
use App\Filament\Resources\Torneos\RelationManagers\ParejasRelationManager;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\Participante;
use App\Models\Torneo;
use App\Models\User;
use Database\Seeders\PlanesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Un Excel con la forma del del Rally 2026: un resumen delante, y la hoja
 * de parejas con familias que comparten correo, una pareja sin compañero,
 * nombres en mayúsculas y un teléfono que no es un teléfono.
 */
function excelDelRally(): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'rally').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($ruta);
    $writer->getCurrentSheet()->setName('Resumen');
    $writer->addRow(Row::fromValues(['Nivel', 'Parejas']));
    $writer->addRow(Row::fromValues(['Iniciación', 2]));

    $writer->addNewSheetAndMakeItCurrent()->setName('Parejas pádel');
    $writer->addRow(Row::fromValues(['Código', 'Nombre del equipo', 'Nivel', 'Participante 1 (capitán)', 'Participante 2', 'Email participante 1', 'Email participante 2', 'Teléfono participante 1', 'Teléfono participante 2', 'Nivel numérico P1', 'Nivel numérico P2']));
    $writer->addRow(Row::fromValues(['GRHBCV', 'Adrianna Hernandez - Ester Hernandez', 'Iniciación', 'Adrianna Hernandez Ros', 'Ester Hernandez Ros', 'hros.adrianna@gmail.com', 'esterhr3@gmail.com', '684182211', '646474238', 0, 0]));
    $writer->addRow(Row::fromValues(['UFKM8U', 'Morales - López', 'Iniciación', 'ELENA MORALES SANCHEZ', 'ALBA LOPEZ MORALES', 'eams01@yahoo.es', 'eams01@yahoo.es', '600111222', 'Error en el formulario', null, 1.5]));
    $writer->addRow(Row::fromValues(['AVS37E', 'Amigó-Villas', 'Intermedio', 'Jonathan Amigó', 'Miriam Vilas Boas', 'jonathan@andorra.ad', 'darashvboas@gmail.com', '376334388', '376339920', null, 0.3]));
    $writer->addRow(Row::fromValues(['Z2FYGE', 'Amigó - Pérez', 'Intermedio', 'Anaïs Amigó', 'Carla Pérez', 'jonathan@andorra.ad', 'carla@example.com', '376000001', '376000002', null, null]));
    $writer->addRow(Row::fromValues(['78UXJJ', 'frade-frade', 'Avanzado', 'david frade manso', 'FALTA COMPAÑERO', 'davidfrademanso@gmail.com', null, '601363223', null, null, null]));
    $writer->addRow(Row::fromValues([null, null, null, null, null, null, null, null, null, null, null]));
    $writer->close();

    return $ruta;
}

beforeEach(function () {
    $this->seed(PlanesSeeder::class);
    Mail::fake();
    usarOrganizador(Organizador::factory()->create());
    $this->propietario = User::factory()->rol(Rol::Propietario)->create();
    $this->torneo = Torneo::factory()->create(['nombre' => 'Rally 2026']);
    $this->intermedio = Categoria::factory()->create(['torneo_id' => $this->torneo->id, 'nombre' => 'Intermedio', 'cupo' => 32]);
});

test('importa parejas y jugadores, crea las categorías que faltan y avisa de lo raro', function () {
    $informe = app(ImportarParejas::class)->handle($this->torneo, excelDelRally(), 'xlsx', $this->propietario);

    expect($informe->errores)->toBe([])
        ->and($informe->parejas)->toBe(4)
        ->and($informe->sinCompanero)->toBe(1)
        ->and($informe->jugadoresNuevos)->toBe(9)
        ->and($informe->categoriasNuevas)->toBe(['Iniciación', 'Avanzado'])
        ->and($this->torneo->categorias()->pluck('nombre')->all())->toBe(['Intermedio', 'Iniciación', 'Avanzado']);

    // Parejas con el nombre de su equipo, confirmadas e importadas.
    $hernandez = Participante::query()->where('nombre', 'Adrianna Hernandez - Ester Hernandez')->sole();
    expect($hernandez->inscripcion->estado)->toBe(EstadoInscripcion::Confirmada)
        ->and($hernandez->inscripcion->origen)->toBe(OrigenInscripcion::Importacion)
        ->and($hernandez->inscripcion->notas)->toBe('Importada · código GRHBCV')
        ->and($hernandez->jugadores->pluck('nombre')->all())->toBe(['Adrianna', 'Ester'])
        ->and($hernandez->jugadores->first()->apellidos)->toBe('Hernandez Ros');

    // Madre e hija con el mismo correo: la segunda, sin correo; los nombres en mayúsculas, como nombre propio.
    $elena = Jugador::query()->where('email', 'eams01@yahoo.es')->sole();
    $alba = Jugador::query()->where('nombre', 'Alba')->sole();
    expect($elena->nombre)->toBe('Elena')->and($elena->apellidos)->toBe('Morales Sanchez')
        ->and($alba->getAttribute('email'))->toBeNull()
        ->and($alba->getAttribute('telefono'))->toBeNull()
        ->and($alba->nivel_declarado)->toBe('1.5');

    // Hermanos con el mismo correo en dos categorías distintas: dos jugadores.
    expect(Jugador::query()->where('nombre', 'Anaïs')->sole()->getAttribute('email'))->toBeNull()
        ->and(Jugador::query()->where('email', 'jonathan@andorra.ad')->sole()->nombre)->toBe('Jonathan');

    // La pareja sin compañero: pendiente, con un jugador, sin ocupar plaza.
    $frade = Participante::query()->where('nombre', 'frade-frade')->sole();
    expect($frade->inscripcion->estado)->toBe(EstadoInscripcion::Pendiente)
        ->and($frade->jugadores)->toHaveCount(1)
        ->and($frade->jugadores->first()->nombre_completo)->toBe('David Frade Manso')
        ->and($frade->inscripcion->notas)->toContain('falta compañero');

    expect(implode("\n", $informe->avisos))->toContain('comparte el correo eams01@yahoo.es con Elena Morales Sanchez')
        ->toContain('comparte el correo jonathan@andorra.ad con Jonathan Amigó')
        ->toContain('«Error en el formulario»');

    // Sin pedirlo, no se manda ningún correo a los jugadores.
    Mail::assertNothingSent();
});

test('importar otra vez el mismo archivo no duplica parejas ni jugadores', function () {
    $ruta = excelDelRally();
    app(ImportarParejas::class)->handle($this->torneo, $ruta, 'xlsx', $this->propietario);
    $jugadores = Jugador::query()->count();

    $otra = app(ImportarParejas::class)->handle($this->torneo, $ruta, 'xlsx', $this->propietario);

    expect($otra->parejas)->toBe(0)
        ->and($otra->yaEstaban)->toBe(5)
        ->and(Jugador::query()->count())->toBe($jugadores)
        ->and(Inscripcion::query()->count())->toBe(5);
});

test('un CSV con punto y coma y cabeceras sencillas también vale; sin las columnas mínimas, se dice', function () {
    $csv = tempnam(sys_get_temp_dir(), 'csv').'.csv';
    file_put_contents($csv, "Categoría;Equipo;Jugador 1;Jugador 2;Correo 1;Correo 2\nIntermedio;Los de siempre;Pablo Gil;Luis Sanz;pablo@example.com;luis@example.com\n");

    $informe = app(ImportarParejas::class)->handle($this->torneo, $csv, 'csv', $this->propietario);
    expect($informe->parejas)->toBe(1)
        ->and(Participante::query()->where('categoria_id', $this->intermedio->id)->sole()->nombre)->toBe('Los de siempre');

    file_put_contents($csv, "Nombre;Apellidos\nPablo;Gil\n");
    $mal = app(ImportarParejas::class)->handle($this->torneo, $csv, 'csv', $this->propietario);
    expect($mal->errores[0])->toContain('Hacen falta al menos');
});

test('a la pareja sin compañero se le añade desde la categoría y queda confirmada', function () {
    app(ImportarParejas::class)->handle($this->torneo, excelDelRally(), 'xlsx', $this->propietario);
    $inscripcion = Participante::query()->where('nombre', 'frade-frade')->sole()->inscripcion;
    $companero = Jugador::factory()->create(['nombre' => 'Pedro', 'apellidos' => 'Frade']);

    Filament::setCurrentPanel('panel');
    $this->actingAs($this->propietario);

    Livewire::test(InscripcionesRelationManager::class, ['ownerRecord' => $inscripcion->categoria, 'pageClass' => EditCategoria::class])
        ->assertActionVisible(TestAction::make('companero')->table($inscripcion))
        ->callAction(TestAction::make('companero')->table($inscripcion), ['companero_id' => $companero->id, 'confirmar' => true])
        ->assertHasNoActionErrors()
        ->assertNotified('Pareja completa');

    expect($inscripcion->refresh()->estado)->toBe(EstadoInscripcion::Confirmada)
        ->and($inscripcion->participante->jugadores()->count())->toBe(2);

    expect(fn () => app(CompletarPareja::class)->handle($inscripcion, Jugador::factory()->create(), true))->toThrow(OperacionNoPermitida::class, 'ya tiene sus dos jugadores');
});

test('desde la ficha del torneo se sube el Excel y se importa', function () {
    Storage::fake('local');
    Filament::setCurrentPanel('panel');
    $this->actingAs($this->propietario);

    Livewire::test(EditTorneo::class, ['record' => $this->torneo->getRouteKey()])
        ->callAction('importar', [
            'archivo' => UploadedFile::fake()->createWithContent('Rally2026_parejas_padel.xlsx', (string) file_get_contents(excelDelRally())),
            'confirmar' => true,
            'avisar' => false,
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Importación hecha')
        ->assertRedirect(EditTorneo::getUrl(['record' => $this->torneo, 'relation' => '1']));

    expect(Inscripcion::query()->count())->toBe(5)
        ->and(Storage::disk('local')->allFiles('importaciones'))->toBe([]);
});

test('las parejas van a la categoría que ya había aunque se llame algo distinto', function () {
    $iniciacion = Categoria::factory()->create(['torneo_id' => $this->torneo->id, 'nombre' => 'Nivel Iniciación', 'nivel' => null]);
    $avanzado = Categoria::factory()->create(['torneo_id' => $this->torneo->id, 'nombre' => 'Mixto', 'nivel' => 'Avanzado']);

    $informe = app(ImportarParejas::class)->handle($this->torneo, excelDelRally(), 'xlsx', $this->propietario);

    expect($informe->categoriasNuevas)->toBe([])
        ->and($this->torneo->categorias()->count())->toBe(3)
        ->and($iniciacion->inscripciones()->count())->toBe(2)
        ->and($avanzado->inscripciones()->count())->toBe(1)
        ->and($this->intermedio->inscripciones()->count())->toBe(2);
});

test('la pestaña Parejas del torneo enseña todas las parejas con su categoría', function () {
    app(ImportarParejas::class)->handle($this->torneo, excelDelRally(), 'xlsx', $this->propietario);
    Filament::setCurrentPanel('panel');
    $this->actingAs($this->propietario);

    expect(ParejasRelationManager::getBadge($this->torneo, EditTorneo::class))->toBe('5');

    $todas = Inscripcion::query()->get();
    $sinCompanero = Inscripcion::query()->where('estado', EstadoInscripcion::Pendiente->value)->sole();

    Livewire::test(ParejasRelationManager::class, ['ownerRecord' => $this->torneo, 'pageClass' => EditTorneo::class])
        ->assertCanSeeTableRecords($todas)
        ->assertSee('Adrianna Hernandez - Ester Hernandez')
        ->assertSee('Iniciación')
        ->assertSee('falta compañero')
        ->filterTable('categoria', $this->intermedio->id)
        ->assertCanSeeTableRecords($this->intermedio->inscripciones()->get())
        ->assertCanNotSeeTableRecords([$sinCompanero]);
});

test('«Confirmar las completas» confirma las pendientes con dos jugadores y respeta el cupo', function () {
    $this->intermedio->update(['cupo' => 1]);
    app(ImportarParejas::class)->handle($this->torneo, excelDelRally(), 'xlsx', $this->propietario, confirmar: false);
    expect(Inscripcion::query()->where('estado', EstadoInscripcion::Pendiente->value)->count())->toBe(5);

    $resultado = app(ConfirmarParejasCompletas::class)->handle($this->torneo, usuario: $this->propietario);

    expect($resultado)->toBe(['confirmadas' => 3, 'sin_plaza' => 1, 'sin_companero' => 1])
        ->and($this->intermedio->inscripciones()->where('estado', EstadoInscripcion::Confirmada->value)->count())->toBe(1)
        ->and(Inscripcion::query()->where('estado', EstadoInscripcion::Pendiente->value)->count())->toBe(2);
    Mail::assertNothingSent();
});

test('el botón está en la pestaña Parejas del torneo y en cada categoría', function () {
    app(ImportarParejas::class)->handle($this->torneo, excelDelRally(), 'xlsx', $this->propietario, confirmar: false);
    Filament::setCurrentPanel('panel');
    $this->actingAs($this->propietario);

    Livewire::test(InscripcionesRelationManager::class, ['ownerRecord' => $this->intermedio, 'pageClass' => EditCategoria::class])
        ->callAction(TestAction::make('confirmarCompletas')->table(), ['avisar' => false])
        ->assertHasNoActionErrors()
        ->assertNotified('2 parejas confirmadas');

    Livewire::test(ParejasRelationManager::class, ['ownerRecord' => $this->torneo, 'pageClass' => EditTorneo::class])
        ->callAction(TestAction::make('confirmarCompletas')->table(), ['avisar' => false])
        ->assertHasNoActionErrors()
        ->assertNotified('2 parejas confirmadas');

    expect(Inscripcion::query()->where('estado', EstadoInscripcion::Confirmada->value)->count())->toBe(4)
        ->and(Inscripcion::query()->where('estado', EstadoInscripcion::Pendiente->value)->count())->toBe(1);
});

test('al importar desde el panel sin tocar nada, las parejas completas entran confirmadas', function () {
    Storage::fake('local');
    Filament::setCurrentPanel('panel');
    $this->actingAs($this->propietario);

    Livewire::test(EditTorneo::class, ['record' => $this->torneo->getRouteKey()])
        ->callAction('importar', [
            'archivo' => UploadedFile::fake()->createWithContent('Rally2026_parejas_padel.xlsx', (string) file_get_contents(excelDelRally())),
        ])
        ->assertHasNoActionErrors();

    expect(Inscripcion::query()->where('estado', EstadoInscripcion::Confirmada->value)->count())->toBe(4);
});
