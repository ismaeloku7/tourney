<?php

namespace App\Filament\Resources\Parejas\Pages;

use App\Actions\Inscripciones\CambiarEstadoDeInscripcion;
use App\Actions\Inscripciones\FijarCabezaDeSerie;
use App\Actions\Inscripciones\MarcarPago;
use App\Actions\Jugadores\CambiarNivel;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Enums\EstadoInscripcion;
use App\Filament\Avisos;
use App\Filament\Resources\Categorias\CategoriaResource;
use App\Filament\Resources\Parejas\CompaneroDePareja;
use App\Filament\Resources\Parejas\MoverParejas;
use App\Filament\Resources\Parejas\ParejaResource;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\RegistroDeCambio;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * La ficha de una pareja en el panel: su nombre, estado, cabeza de serie,
 * pago y notas, y los datos de sus jugadores (nombre, correo, teléfono y
 * nivel). Los cambios de estado, pago y cabeza de serie pasan por las
 * mismas reglas que en la categoría.
 */
class EditPareja extends EditRecord
{
    protected static string $resource = ParejaResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->inscripcion()->participante->nombre;
    }

    public function getSubheading(): string
    {
        $categoria = $this->inscripcion()->categoria;

        return $categoria->torneo->nombre.' · '.$categoria->nombre;
    }

    protected function getHeaderActions(): array
    {
        return [
            CompaneroDePareja::accion()->after(fn () => $this->refrescar()),
            MoverParejas::accion()->after(fn () => $this->refrescar()),
            Action::make('categoria')->label('Ver su categoría')->icon(Heroicon::OutlinedRectangleStack)->color('gray')
                ->url(fn (): string => CategoriaResource::getUrl('edit', ['record' => $this->inscripcion()->categoria_id])),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $jugadores = $this->inscripcion()->participante->jugadores;

        return $schema->columns(1)->components([
            Section::make('Pareja')->columns(2)->components([
                TextInput::make('nombre')->label('Nombre de la pareja')->required()->maxLength(160)->columnSpanFull()
                    ->helperText('El que sale en los cuadros, los horarios y la web.'),
                Select::make('estado')->label('Estado')->options(EstadoInscripcion::opciones())->required()
                    ->helperText('Confirmar respeta el cupo; dar de baja la retira de la categoría.'),
                TextInput::make('cabeza_de_serie')->label('Cabeza de serie')->integer()->minValue(1)->maxValue(128)
                    ->helperText('Solo en parejas confirmadas. En blanco, no lo es.'),
                Toggle::make('pagado')->label('Pagado')->inline(false),
                TextInput::make('pago_notas')->label('Notas del pago')->maxLength(255)->placeholder('Bizum 12/04, efectivo…'),
                Textarea::make('notas')->label('Notas')->rows(2)->maxLength(2000)->columnSpanFull(),
            ]),
            Section::make('Jugadores')
                ->description('Sus datos son los de la base de jugadores: si juegan otros torneos, cambian también ahí.')
                ->components([
                    ...$jugadores->values()->map(fn (Jugador $j, int $i): Fieldset => Fieldset::make($i === 0 ? 'Jugador 1 (capitán)' : 'Jugador 2')
                        ->columns(3)
                        ->schema([
                            TextInput::make("jugadores.{$j->id}.nombre")->label('Nombre')->required()->maxLength(80),
                            TextInput::make("jugadores.{$j->id}.apellidos")->label('Apellidos')->required()->maxLength(120),
                            TextInput::make("jugadores.{$j->id}.nivel")->label('Nivel')->placeholder('3 o 2,5')->maxLength(5)
                                ->regex('/^(10([.,]0{1,2})?|\d([.,]\d{1,2})?)$/')
                                ->validationMessages(['regex' => 'Un número del 0 al 10, como 3 o 2,5.']),
                            TextInput::make("jugadores.{$j->id}.email")->label('Correo electrónico')->email()->maxLength(190)->autocomplete('off')
                                ->rule(Rule::unique(Jugador::class, 'email')->ignore($j->id))
                                ->validationMessages(['unique' => 'Ese correo ya es de otro jugador.'])
                                ->columnSpan(2),
                            TextInput::make("jugadores.{$j->id}.telefono")->label('Teléfono')->tel()->maxLength(30),
                        ]))->all(),
                    Text::make('Falta el compañero: añádelo con «Añadir compañero», arriba.')
                        ->visible($jugadores->count() < 2),
                ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $inscripcion = $this->inscripcion();

        return [
            'nombre' => $inscripcion->participante->nombre,
            'estado' => $inscripcion->estado->value,
            'cabeza_de_serie' => $inscripcion->participante->cabeza_de_serie,
            'pagado' => $inscripcion->pago_marcado_en !== null,
            'pago_notas' => $inscripcion->pago_notas,
            'notas' => $inscripcion->notas,
            'jugadores' => $inscripcion->participante->jugadores->mapWithKeys(fn (Jugador $j): array => [$j->id => [
                'nombre' => $j->nombre,
                'apellidos' => $j->apellidos,
                'nivel' => $j->nivel_declarado,
                'email' => $j->getAttribute('email'),
                'telefono' => $j->getAttribute('telefono'),
            ]])->all(),
        ];
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof Inscripcion);
        $usuario = Filament::auth()->user();
        assert($usuario instanceof User);
        $texto = fn (mixed $valor): ?string => is_string($valor) && trim($valor) !== '' ? trim($valor) : null;

        try {
            DB::transaction(function () use ($record, $data, $usuario, $texto): void {
                $participante = $record->participante;
                $antes = ['nombre' => $participante->nombre, 'notas' => $record->notas];

                $participante->update(['nombre' => (string) $texto($data['nombre'] ?? null)]);
                $record->update(['notas' => $texto($data['notas'] ?? null)]);

                /** @var array<int|string, array<string, mixed>> $datosDeJugadores */
                $datosDeJugadores = is_array($data['jugadores'] ?? null) ? $data['jugadores'] : [];
                foreach ($participante->jugadores()->get() as $jugador) {
                    $datos = $datosDeJugadores[$jugador->id] ?? null;
                    if (! is_array($datos)) {
                        continue;
                    }

                    $email = $texto($datos['email'] ?? null);
                    $jugador->update([
                        'nombre' => (string) $texto($datos['nombre'] ?? null),
                        'apellidos' => (string) $texto($datos['apellidos'] ?? null),
                        'email' => $email !== null ? mb_strtolower($email) : null,
                        'telefono' => $texto($datos['telefono'] ?? null),
                    ]);
                    app(CambiarNivel::class)->porLaOrganizacion($jugador, $texto($datos['nivel'] ?? null), $usuario);
                }

                $estado = EstadoInscripcion::tryFrom((string) ($data['estado'] ?? ''));
                if ($estado !== null && $estado !== $record->estado) {
                    app(CambiarEstadoDeInscripcion::class)->handle($record, $estado, $usuario, avisar: false);
                }

                $pagado = (bool) ($data['pagado'] ?? false);
                $notasDelPago = $texto($data['pago_notas'] ?? null);
                if ($pagado !== ($record->pago_marcado_en !== null) || $notasDelPago !== $record->pago_notas) {
                    app(MarcarPago::class)->handle($record, $pagado, $notasDelPago, $usuario);
                }

                $cabeza = filled($data['cabeza_de_serie'] ?? null) ? (int) $data['cabeza_de_serie'] : null;
                if ($cabeza !== $participante->refresh()->cabeza_de_serie) {
                    app(FijarCabezaDeSerie::class)->handle($participante, $cabeza, $usuario);
                }

                $despues = ['nombre' => $participante->refresh()->nombre, 'notas' => $record->notas];
                if ($antes !== $despues) {
                    RegistroDeCambio::anotar('editar_pareja', $record, $usuario, $antes, $despues);
                }
            });
        } catch (ErrorDeCompeticion $e) {
            Avisos::error('No se ha podido guardar', [$e->getMessage()]);
            $this->halt();
        }

        return $record->refresh();
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Pareja guardada';
    }

    private function refrescar(): void
    {
        $this->record = $this->inscripcion()->refresh();
        $this->fillForm();
    }

    private function inscripcion(): Inscripcion
    {
        $record = $this->getRecord();
        assert($record instanceof Inscripcion);

        return $record;
    }
}
