<?php

namespace App\Filament\Resources\Categorias\Schemas;

use App\Competicion\Datos\ConfiguracionDeEliminatoria;
use App\Competicion\Datos\ConfiguracionDeGrupos;
use App\Competicion\Datos\Grupo as GrupoDto;
use App\Competicion\Errores\ConfiguracionInvalida;
use App\Competicion\EsquemaDeCuadro;
use App\Enums\CriterioDeDesempate;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoParticipante;
use App\Enums\ModoDeSorteo;
use App\Enums\OrigenDeConsolacion;
use App\Enums\ReglaDeCruces;
use App\Enums\TipoDeFase;
use App\Models\Categoria;
use App\Models\Fase;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;

/**
 * El formulario de una fase: los campos del tipo elegido, guardados en
 * `fases.configuracion` tal como los deja el DTO del motor (que es quien
 * valida; el formulario solo guía).
 */
class FaseForm
{
    /** @return list<Component> */
    public static function campos(): array
    {
        return [
            Section::make('Fase')->columns(2)->components([
                Select::make('tipo')->label('Tipo')->options(TipoDeFase::opciones())->required()->live()
                    ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                        $tipo = self::tipo($state);
                        if ($tipo !== null && blank($get('nombre'))) {
                            $set('nombre', $tipo->etiqueta());
                        }
                    }),
                TextInput::make('nombre')->label('Nombre')->required()->maxLength(80),
                TextInput::make('orden')->label('Orden')->integer()->minValue(1)->required()
                    ->default(fn (RelationManager $livewire): int => self::categoria($livewire)->fases()->count() + 1)
                    ->helperText('Las fases se juegan en este orden; la segunda recibe a los clasificados de la primera.'),
                Select::make('formato_partido_id')->label('Formato de partido')->relationship('formatoPartido', 'nombre')->placeholder('El de la categoría'),
                Select::make('fase_origen_id')->label('Toma a las parejas de')
                    ->options(fn (RelationManager $livewire, ?Fase $record): array => self::categoria($livewire)->fases()
                        ->whereKeyNot($record->id ?? 0)
                        ->orderBy('orden')->pluck('nombre', 'id')->all())
                    ->visible(fn (Get $get): bool => self::tipo($get('tipo')) === TipoDeFase::Consolacion)
                    ->required(fn (Get $get): bool => self::tipo($get('tipo')) === TipoDeFase::Consolacion)
                    ->helperText('La fase de grupos de la que no se clasificaron, o el cuadro cuyos perdedores de primera ronda recoge.'),
            ]),

            Section::make('Grupos')
                ->description('Round robin dentro de cada grupo; con la clasificación se decide quién pasa.')
                ->columns(2)
                ->visible(fn (Get $get): bool => self::tipo($get('tipo'))?->esDeGrupos() ?? false)
                ->components([
                    Radio::make('reparto')->label('Repartir las parejas')
                        ->options(['tamano' => 'Por tamaño de grupo', 'numero' => 'Por número de grupos'])
                        ->default('tamano')->live()
                        ->visible(fn (Get $get): bool => self::tipo($get('tipo')) === TipoDeFase::Grupos),
                    TextInput::make('configuracion.tamano_de_grupo')->label('Parejas por grupo')->integer()->minValue(2)->maxValue(32)->default(4)
                        ->visible(fn (Get $get): bool => self::tipo($get('tipo')) === TipoDeFase::Grupos && $get('reparto') !== 'numero')
                        ->helperText('Si no cuadra, el reparto deja algún grupo con una pareja más.'),
                    TextInput::make('configuracion.numero_de_grupos')->label('Número de grupos')->integer()->minValue(1)->maxValue(64)
                        ->visible(fn (Get $get): bool => self::tipo($get('tipo')) === TipoDeFase::Grupos && $get('reparto') === 'numero'),
                    Select::make('configuracion.modo_de_sorteo')->label('Sorteo')
                        ->options([ModoDeSorteo::Aleatorio->value => ModoDeSorteo::Aleatorio->etiqueta(), ModoDeSorteo::CabezasDeSerie->value => ModoDeSorteo::CabezasDeSerie->etiqueta(), ModoDeSorteo::PorNiveles->value => ModoDeSorteo::PorNiveles->etiqueta()])
                        ->default(ModoDeSorteo::Aleatorio->value)->required()
                        ->visible(fn (Get $get): bool => self::tipo($get('tipo')) === TipoDeFase::Grupos)
                        ->helperText('Con cabezas de serie, cada una encabeza un grupo. Por niveles, bombos de más a menos nivel medio, uno por grupo y al azar en cada bombo. Al sortear se puede cambiar, ordenar las cabezas de serie, fijar parejas en cada grupo o hacerlo todo a mano.'),
                    Toggle::make('configuracion.ida_y_vuelta')->label('Ida y vuelta')->default(false)->inline(false),
                    TextInput::make('configuracion.partidos_por_participante')->label('Partidos por pareja')->integer()->minValue(1)
                        ->helperText('En blanco, todas contra todas. Si se limita, cada pareja juega solo esos partidos.'),
                    TextInput::make('configuracion.puntuacion.victoria')->label('Puntos por victoria')->integer()->minValue(0)->default(2)->required(),
                    TextInput::make('configuracion.puntuacion.derrota')->label('Puntos por derrota')->integer()->minValue(0)->default(0)->required(),
                    TextInput::make('configuracion.puntuacion.por_set_ganado')->label('Puntos por set ganado')->integer()->minValue(0)->helperText('En blanco, no suman.'),
                    Select::make('configuracion.criterios_de_desempate')->label('Desempates, en orden')->multiple()->required()
                        ->options(CriterioDeDesempate::opciones())
                        ->default(array_map(fn (CriterioDeDesempate $c): string => $c->value, CriterioDeDesempate::porDefecto()))
                        ->helperText('Se aplican en el orden en que están elegidos.')
                        ->columnSpanFull(),
                    TextInput::make('configuracion.clasificados.por_grupo')->label('Clasificados por grupo')->integer()->minValue(0)->default(2)->required()
                        ->visible(fn (Get $get): bool => self::tipo($get('tipo')) === TipoDeFase::Grupos),
                    TextInput::make('configuracion.clasificados.cuantos_mejores')->label('Mejores de una posición')->integer()->minValue(0)->default(0)
                        ->visible(fn (Get $get): bool => self::tipo($get('tipo')) === TipoDeFase::Grupos)
                        ->helperText('Además de los de cada grupo: por ejemplo, los 2 mejores terceros.'),
                    TextInput::make('configuracion.clasificados.mejores_de_posicion')->label('… de qué posición')->integer()->minValue(1)
                        ->visible(fn (Get $get): bool => self::tipo($get('tipo')) === TipoDeFase::Grupos && (int) $get('configuracion.clasificados.cuantos_mejores') > 0),
                ]),

            Section::make('Cuadro')
                ->description('Eliminatoria directa. El tamaño se ajusta solo a los que lleguen, con byes para los cabezas de serie.')
                ->columns(2)
                ->visible(fn (Get $get): bool => self::tipo($get('tipo'))?->esDeCuadro() ?? false)
                ->components([
                    Radio::make('configuracion.regla_de_cruces')->label('Cómo se arma el cuadro')
                        ->options([
                            ReglaDeCruces::EntreGrupos->value => 'Automático: los primeros contra los segundos de otro grupo',
                            ReglaDeCruces::Sorteo->value => 'Por sorteo',
                            ReglaDeCruces::APosiciones->value => 'A mano: tú decides dónde va cada posición (1º A contra 2º C…)',
                        ])
                        ->default(ReglaDeCruces::EntreGrupos->value)->required()->live()->columnSpanFull(),
                    Select::make('configuracion.tamano_de_cuadro')->label('Tamaño del cuadro')
                        ->options([2 => '2 (final)', 4 => '4', 8 => '8', 16 => '16', 32 => '32', 64 => '64', 128 => '128'])
                        ->placeholder('El mínimo en el que caben')->live()
                        ->visible(fn (Get $get): bool => $get('configuracion.regla_de_cruces') !== ReglaDeCruces::APosiciones->value),
                    Repeater::make('configuracion.cruces_por_posicion')->label('Cruces de la primera ronda')
                        ->schema(function (RelationManager $livewire, ?Fase $record): array {
                            $posiciones = self::posiciones($livewire, $record);

                            return [
                                Select::make('local')->hiddenLabel()->options($posiciones)->placeholder('Bye (pasa sin jugar)'),
                                Select::make('visitante')->hiddenLabel()->options($posiciones)->placeholder('Bye (pasa sin jugar)'),
                            ];
                        })
                        ->columns(2)->defaultItems(0)->reorderable()->addActionLabel('Añadir cruce')->live()
                        ->hintAction(Action::make('proponer')->label('Proponer cruces')
                            ->action(function (Set $set, RelationManager $livewire, ?Fase $record): void {
                                $set('configuracion.cruces_por_posicion', self::cruceSugerido(array_keys(self::posiciones($livewire, $record))));
                            }))
                        ->helperText(fn (RelationManager $livewire, ?Fase $record): string => self::posiciones($livewire, $record) === []
                            ? 'Añada antes la fase de grupos de la que salen las parejas.'
                            : 'Una fila por partido, en el orden del cuadro: los ganadores de las filas 1 y 2 se cruzan en la ronda siguiente, los de la 3 y 4 entre sí, y así. Cada posición que pasa tiene que estar en una fila; un hueco vacío es un bye. Se rellena con quien quede en cada posición al cerrar los grupos.')
                        ->visible(fn (Get $get): bool => $get('configuracion.regla_de_cruces') === ReglaDeCruces::APosiciones->value)
                        ->columnSpanFull(),
                    Toggle::make('configuracion.evitar_reencuentros')->label('Evitar reencuentros de la fase anterior')->default(true)->inline(false)->live()
                        ->visible(fn (Get $get): bool => $get('configuracion.regla_de_cruces') === ReglaDeCruces::EntreGrupos->value),
                    Toggle::make('configuracion.tercer_y_cuarto_puesto')->label('Partido por el tercer puesto')->default(false)->inline(false)->live(),
                    Text::make(fn (Get $get, RelationManager $livewire, ?Fase $record): HtmlString => self::esquema($get, $livewire, $record))
                        ->visible(fn (Get $get): bool => self::tipo($get('tipo')) === TipoDeFase::Eliminatoria)
                        ->columnSpanFull(),
                    Select::make('configuracion.consolacion')->label('Consolación')
                        ->options(OrigenDeConsolacion::opciones())->default(OrigenDeConsolacion::Ninguna->value)
                        ->visible(fn (Get $get): bool => self::tipo($get('tipo')) === TipoDeFase::Eliminatoria)
                        ->helperText('Informativo: la consolación se añade como otra fase que toma a sus parejas de esta.'),
                ]),
        ];
    }

    /**
     * Deja los datos como el motor los quiere: solo la configuración del
     * tipo, validada por su DTO.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ConfiguracionInvalida
     */
    public static function normalizar(array $data): array
    {
        $tipo = self::tipo($data['tipo'] ?? null) ?? throw new ConfiguracionInvalida(['Hay que elegir el tipo de fase.']);
        $configuracion = array_filter((array) ($data['configuracion'] ?? []), fn (mixed $v): bool => $v !== null && $v !== '');

        if ($tipo->esDeGrupos()) {
            if ($tipo === TipoDeFase::Liga) {
                $configuracion['numero_de_grupos'] = 1;
                unset($configuracion['tamano_de_grupo']);
                $configuracion['modo_de_sorteo'] = ModoDeSorteo::Aleatorio->value;
                $configuracion['clasificados'] = ['por_grupo' => 0, 'cuantos_mejores' => 1, 'mejores_de_posicion' => 1];
            } elseif (isset($configuracion['numero_de_grupos'], $configuracion['tamano_de_grupo'])) {
                // Los dos vienen solo si el usuario cambió de reparto a medias: manda el elegido.
                unset($configuracion[($data['reparto'] ?? 'tamano') === 'numero' ? 'tamano_de_grupo' : 'numero_de_grupos']);
            }

            $configuracion['puntuacion'] = array_filter((array) ($configuracion['puntuacion'] ?? []), fn (mixed $v): bool => $v !== null && $v !== '');
            $configuracion['clasificados'] = array_filter((array) ($configuracion['clasificados'] ?? []), fn (mixed $v): bool => $v !== null && $v !== '');
            $data['configuracion'] = ConfiguracionDeGrupos::desdeArray($configuracion)->toArray();
        } else {
            if ($tipo !== TipoDeFase::Eliminatoria) {
                $configuracion['consolacion'] = OrigenDeConsolacion::Ninguna->value;
            }

            // Las filas del formulario («local», «visitante») como pares; un hueco vacío es un bye.
            if (($configuracion['regla_de_cruces'] ?? null) === ReglaDeCruces::APosiciones->value) {
                $configuracion['cruces_por_posicion'] = array_values(array_map(
                    fn (mixed $fila): array => [filled(((array) $fila)['local'] ?? null) ? (string) ((array) $fila)['local'] : null, filled(((array) $fila)['visitante'] ?? null) ? (string) ((array) $fila)['visitante'] : null],
                    (array) ($configuracion['cruces_por_posicion'] ?? []),
                ));
            } else {
                unset($configuracion['cruces_por_posicion']);
            }

            $data['configuracion'] = ConfiguracionDeEliminatoria::desdeArray($configuracion)->toArray();
        }

        if ($tipo !== TipoDeFase::Consolacion) {
            $data['fase_origen_id'] = null;
        }

        unset($data['reparto']);

        return $data;
    }

    /**
     * Al editar: el reparto que estaba en uso, para que salga el campo correcto.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function alRellenar(array $data): array
    {
        $configuracion = (array) ($data['configuracion'] ?? []);
        $data['reparto'] = isset($configuracion['numero_de_grupos']) && ! isset($configuracion['tamano_de_grupo']) ? 'numero' : 'tamano';

        if (is_array($configuracion['cruces_por_posicion'] ?? null)) {
            $configuracion['cruces_por_posicion'] = array_map(
                fn (mixed $par): array => ['local' => ((array) $par)[0] ?? null, 'visitante' => ((array) $par)[1] ?? null],
                array_values($configuracion['cruces_por_posicion']),
            );
            $data['configuracion'] = $configuracion;
        }

        return $data;
    }

    /** Lo que se enseña en la tabla: «4 por grupo · pasan 2», «Cuadro de 16»… */
    public static function resumen(Fase $fase): string
    {
        try {
            $configuracion = $fase->configuracion();
        } catch (ConfiguracionInvalida) {
            return 'Configuración incompleta';
        }

        if ($configuracion instanceof ConfiguracionDeGrupos) {
            if ($fase->tipo === TipoDeFase::Liga) {
                return 'Todas contra todas'.($configuracion->idaYVuelta ? ' · ida y vuelta' : '');
            }

            $reparto = $configuracion->numeroDeGrupos !== null ? "{$configuracion->numeroDeGrupos} grupos" : "{$configuracion->tamanoDeGrupo} por grupo";
            $pasan = "pasan {$configuracion->clasificados->porGrupo} por grupo";
            if ($configuracion->clasificados->cuantosMejores > 0) {
                $pasan .= " y los {$configuracion->clasificados->cuantosMejores} mejores {$configuracion->clasificados->mejoresDePosicion}º";
            }

            return "{$reparto} · {$pasan}".($configuracion->idaYVuelta ? ' · ida y vuelta' : '');
        }

        $tamano = $configuracion->tamanoDeCuadro !== null ? "Cuadro de {$configuracion->tamanoDeCuadro}" : 'Cuadro al tamaño que haga falta';

        return $tamano.($configuracion->tercerYCuartoPuesto ? ' · con tercer puesto' : '');
    }

    /**
     * El esquema del cuadro con lo que hay en el formulario: rondas,
     * cruces y quién llega a cada hueco. Sin fase de grupos antes, con los
     * cabezas de serie de las parejas confirmadas.
     */
    public static function esquema(Get $get, RelationManager $livewire, ?Fase $fase): HtmlString
    {
        $posiciones = self::posiciones($livewire, $fase);
        $codigos = array_keys($posiciones);
        // «Mejor 3º nº 1»: de qué posición son los mejores.
        $mejoresDe = preg_match('/^Mejor (\d+)º/', $posiciones['M1'] ?? '', $partes) === 1 ? (int) $partes[1] : null;
        $aviso = null;
        $datos = (array) $get('configuracion');

        if ($codigos === []) {
            $parejas = self::categoria($livewire)->participantes()->where('estado', EstadoParticipante::Activo->value)
                ->whereHas('inscripcion', fn ($q) => $q->where('estado', EstadoInscripcion::Confirmada->value))->count();
            $codigos = array_map(fn (int $i): string => 'CS'.$i, range(1, max(2, $parejas)));
            $aviso = 'Sin fase de grupos antes: con las '.$parejas.' parejas confirmadas, por orden de cabeza de serie.';
        } elseif (($datos['regla_de_cruces'] ?? null) === ReglaDeCruces::EntreGrupos->value) {
            $aviso = 'Orientativo: entre los primeros (y entre los segundos) el orden sale de sus resultados en los grupos.';
        }

        if (($datos['regla_de_cruces'] ?? null) === ReglaDeCruces::APosiciones->value && array_filter((array) ($datos['cruces_por_posicion'] ?? [])) === []) {
            $esquema = ['rondas' => [], 'tercero' => null, 'error' => 'Añade los cruces de la primera ronda (o pulsa «Proponer cruces») para ver el cuadro.'];
        } else {
            try {
                $esquema = app(EsquemaDeCuadro::class)->armar($codigos, ConfiguracionDeEliminatoria::desdeArray($datos), $mejoresDe);
            } catch (ConfiguracionInvalida $e) {
                $esquema = ['rondas' => [], 'tercero' => null, 'error' => implode(' ', $e->errores)];
            }
        }

        return new HtmlString(view('filament.panel.competicion.esquema-cuadro', [...$esquema, 'aviso' => $aviso])->render());
    }

    /**
     * Las posiciones que pasan de la fase de grupos anterior: «1A» → «1º
     * grupo A»…, y los mejores de una posición, «M1» → «Mejor 3º nº 1». Con
     * los grupos ya sorteados, sus letras; si no, las que saldrán.
     *
     * @return array<string, string>
     */
    public static function posiciones(RelationManager $livewire, ?Fase $fase): array
    {
        $categoria = self::categoria($livewire);
        $orden = $fase->orden ?? ((int) $categoria->fases()->max('orden') + 1);
        $grupos = $categoria->fases()->where('orden', '<', $orden)
            ->whereIn('tipo', [TipoDeFase::Grupos->value, TipoDeFase::Liga->value])
            ->orderByDesc('orden')->first();

        if ($grupos === null) {
            return [];
        }

        try {
            $configuracion = $grupos->configuracionDeGrupos();
        } catch (ConfiguracionInvalida) {
            return [];
        }

        $letras = $grupos->grupos()->orderBy('orden')->pluck('nombre')->map(fn (mixed $n): string => (string) $n)->all();
        if ($letras === []) {
            $parejas = $categoria->participantes()->where('estado', EstadoParticipante::Activo->value)
                ->whereHas('inscripcion', fn ($q) => $q->where('estado', EstadoInscripcion::Confirmada->value))->count();
            $cuantos = $configuracion->numeroDeGrupos ?? max(1, intdiv(max(1, $parejas), $configuracion->tamanoDeGrupo ?? 4));
            $letras = array_map(fn (int $i): string => GrupoDto::nombrePorOrden($i), range(1, $cuantos));
        }

        $opciones = [];
        for ($posicion = 1; $posicion <= $configuracion->clasificados->porGrupo; $posicion++) {
            foreach ($letras as $letra) {
                $opciones[$posicion.$letra] = $posicion.'º grupo '.$letra;
            }
        }
        for ($k = 1; $k <= $configuracion->clasificados->cuantosMejores; $k++) {
            $opciones['M'.$k] = ConfiguracionDeEliminatoria::etiquetaDePosicion('M'.$k, $configuracion->clasificados->mejoresDePosicion);
        }

        return $opciones;
    }

    /**
     * Una propuesta de partida: el mejor contra el peor (1º A contra el
     * último segundo…), con byes a los primeros si no cuadra.
     *
     * @param  list<string>  $codigos  En orden: primeros, segundos… y los mejores.
     * @return list<array{local: string|null, visitante: string|null}>
     */
    public static function cruceSugerido(array $codigos): array
    {
        $n = count($codigos);
        if ($n < 2) {
            return [];
        }

        $tamano = 2;
        while ($tamano < $n) {
            $tamano *= 2;
        }

        $filas = [];
        for ($i = 0; $i < $tamano / 2; $i++) {
            $filas[] = ['local' => $codigos[$i] ?? null, 'visitante' => $codigos[$tamano - 1 - $i] ?? null];
        }

        return $filas;
    }

    private static function tipo(mixed $valor): ?TipoDeFase
    {
        return $valor instanceof TipoDeFase ? $valor : TipoDeFase::tryFrom((string) $valor);
    }

    private static function categoria(RelationManager $livewire): Categoria
    {
        $categoria = $livewire->getOwnerRecord();
        assert($categoria instanceof Categoria);

        return $categoria;
    }
}
