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
use App\Models\Grupo;
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
                        ->default(ReglaDeCruces::EntreGrupos->value)->required()->live()->columnSpanFull()
                        // Al pasar a mano sin cruces, se parte del cuadro automático para cambiar solo lo que haga falta.
                        ->afterStateUpdated(function (?string $state, Get $get, Set $set, RelationManager $livewire, ?Fase $record): void {
                            if ($state === ReglaDeCruces::APosiciones->value && array_filter((array) $get('configuracion.cruces_por_posicion')) === []) {
                                $set('configuracion.cruces_por_posicion', self::crucesAutomaticos($livewire, $record));
                            }
                        }),
                    Select::make('configuracion.mejores_de')->label('Los «mejores» que pasan son los…')
                        ->options(fn (RelationManager $livewire, ?Fase $record): array => self::opcionesDeMejores($livewire, $record))
                        ->default(3)->live()
                        ->afterStateHydrated(function (Select $component, mixed $state, RelationManager $livewire, ?Fase $record): void {
                            if (blank($state)) {
                                $component->state(self::mejoresDeAhora($livewire, $record));
                            }
                        })
                        ->helperText('Para los cruces con «Mejor nº 1», «Mejor nº 2»…: de qué posición de grupo son.')
                        ->visible(fn (Get $get): bool => $get('configuracion.regla_de_cruces') === ReglaDeCruces::APosiciones->value),
                    Select::make('configuracion.tamano_de_cuadro')->label('Tamaño del cuadro')
                        ->options([2 => '2 (final)', 4 => '4', 8 => '8', 16 => '16', 32 => '32', 64 => '64', 128 => '128'])
                        ->placeholder('El mínimo en el que caben')->live()
                        ->visible(fn (Get $get): bool => $get('configuracion.regla_de_cruces') !== ReglaDeCruces::APosiciones->value),
                    Repeater::make('configuracion.cruces_por_posicion')->label('Cruces de la primera ronda')
                        ->schema(function (RelationManager $livewire, ?Fase $record): array {
                            $opciones = fn (Get $get): array => self::posicionesPosibles($livewire, $record, is_numeric($get('../../mejores_de')) ? (int) $get('../../mejores_de') : null);

                            return [
                                Select::make('local')->hiddenLabel()->options($opciones)->searchable()->placeholder('Bye (pasa sin jugar)')->live(),
                                Select::make('visitante')->hiddenLabel()->options($opciones)->searchable()->placeholder('Bye (pasa sin jugar)')->live(),
                            ];
                        })
                        ->columns(2)->defaultItems(0)->reorderable()->itemNumbers()->addActionLabel('Añadir cruce')->live()
                        ->hintAction(Action::make('proponer')->label('Partir del cuadro automático')
                            ->action(function (Set $set, RelationManager $livewire, ?Fase $record): void {
                                $set('configuracion.cruces_por_posicion', self::crucesAutomaticos($livewire, $record));
                            }))
                        ->helperText(fn (RelationManager $livewire, ?Fase $record): string => self::faseDeGrupos(self::categoria($livewire), $record) === null
                            ? 'Añada antes la fase de grupos de la que salen las parejas.'
                            : 'Una fila por partido de la primera ronda, en el orden del esquema de abajo (fila 1 = primer cruce…). En cada hueco, cualquier posición: «3º B», «Mejor nº 1»… o nada para un bye. Al guardar, la fase de grupos pasa a clasificar justo a los que pongas aquí.')
                        ->visible(fn (Get $get): bool => $get('configuracion.regla_de_cruces') === ReglaDeCruces::APosiciones->value)
                        ->columnSpanFull(),
                    Toggle::make('configuracion.evitar_reencuentros')->label('Evitar reencuentros de la fase anterior')->default(true)->inline(false)->live()
                        ->visible(fn (Get $get): bool => $get('configuracion.regla_de_cruces') === ReglaDeCruces::EntreGrupos->value),
                    Toggle::make('configuracion.tercer_y_cuarto_puesto')->label('Partido por el tercer puesto')->default(false)->inline(false)->live(),
                    Text::make(fn (Get $get, RelationManager $livewire, ?Fase $record): HtmlString => self::esquema((array) $get('configuracion'), $livewire, $record))
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

    /** La fase de grupos (o liga) de la que salen las parejas de esta fase: la anterior más cercana. */
    public static function faseDeGrupos(Categoria $categoria, ?Fase $fase): ?Fase
    {
        $orden = $fase->orden ?? ((int) $categoria->fases()->max('orden') + 1);

        return $categoria->fases()->where('orden', '<', $orden)
            ->whereIn('tipo', [TipoDeFase::Grupos->value, TipoDeFase::Liga->value])
            ->orderByDesc('orden')->first();
    }

    /**
     * Las letras de los grupos y cuántas parejas tiene el más grande: los
     * hechos o, sin sortear, los que saldrán.
     *
     * @return array{0: list<string>, 1: int}
     */
    public static function letrasYTamano(Categoria $categoria, Fase $grupos): array
    {
        $hechos = $grupos->grupos()->withCount('participantes')->orderBy('orden')->get();
        if ($hechos->isNotEmpty()) {
            return [array_values($hechos->map(fn (Grupo $g): string => (string) $g->nombre)->all()), max(2, (int) $hechos->max('participantes_count'))];
        }

        try {
            $configuracion = $grupos->configuracionDeGrupos();
        } catch (ConfiguracionInvalida) {
            return [[], 0];
        }

        $parejas = max(1, $categoria->participantes()->where('estado', EstadoParticipante::Activo->value)
            ->whereHas('inscripcion', fn ($q) => $q->where('estado', EstadoInscripcion::Confirmada->value))->count());
        $cuantos = max(1, $configuracion->numeroDeGrupos ?? intdiv($parejas, $configuracion->tamanoDeGrupo ?? 4));

        return [array_map(fn (int $i): string => GrupoDto::nombrePorOrden($i), range(1, $cuantos)), max(2, (int) ceil($parejas / $cuantos))];
    }

    /**
     * Todo lo que se puede poner en un cruce a mano, pase o no ahora: cada
     * posición de cada grupo («3B» → «3º grupo B») y los mejores de una
     * posición («M1» → «Mejor 3º nº 1»).
     *
     * @return array<string, string>
     */
    public static function posicionesPosibles(RelationManager $livewire, ?Fase $fase, ?int $mejoresDe): array
    {
        $categoria = self::categoria($livewire);
        $grupos = self::faseDeGrupos($categoria, $fase);
        if ($grupos === null) {
            return [];
        }

        [$letras, $tamano] = self::letrasYTamano($categoria, $grupos);
        $mejoresDe ??= self::mejoresDeAhora($livewire, $fase);

        $opciones = [];
        for ($posicion = 1; $posicion <= $tamano; $posicion++) {
            foreach ($letras as $letra) {
                $opciones[$posicion.$letra] = $posicion.'º grupo '.$letra;
            }
        }
        for ($k = 1; $k <= count($letras); $k++) {
            $opciones['M'.$k] = ConfiguracionDeEliminatoria::etiquetaDePosicion('M'.$k, $mejoresDe);
        }

        return $opciones;
    }

    /** @return array<int, string> */
    public static function opcionesDeMejores(RelationManager $livewire, ?Fase $fase): array
    {
        $categoria = self::categoria($livewire);
        $grupos = self::faseDeGrupos($categoria, $fase);
        $tamano = $grupos !== null ? self::letrasYTamano($categoria, $grupos)[1] : 4;

        $opciones = [];
        for ($posicion = 2; $posicion <= max(2, $tamano); $posicion++) {
            $opciones[$posicion] = 'Mejores '.$posicion.'º';
        }

        return $opciones;
    }

    /** De qué posición son ahora los mejores que pasan (o la siguiente a las que pasan enteras). */
    public static function mejoresDeAhora(RelationManager $livewire, ?Fase $fase): int
    {
        $grupos = self::faseDeGrupos(self::categoria($livewire), $fase);

        try {
            $clasificados = $grupos?->configuracionDeGrupos()->clasificados;
        } catch (ConfiguracionInvalida) {
            $clasificados = null;
        }

        return $clasificados === null ? 3 : ($clasificados->mejoresDePosicion ?? $clasificados->porGrupo + 1);
    }

    /**
     * Los cruces de la primera ronda del cuadro automático, como filas del
     * formulario, para partir de ellos al hacerlo a mano.
     *
     * @return list<array{local: string|null, visitante: string|null}>
     */
    public static function crucesAutomaticos(RelationManager $livewire, ?Fase $fase): array
    {
        $codigos = array_keys(self::posiciones($livewire, $fase));
        $esquema = app(EsquemaDeCuadro::class)->armar($codigos, new ConfiguracionDeEliminatoria);

        return array_map(fn (array $p): array => ['local' => $p['localCodigo'], 'visitante' => $p['visitanteCodigo']], $esquema['rondas'][0]['partidos'] ?? []);
    }

    /**
     * Quién tiene que pasar de la fase de grupos para que salgan los cruces
     * puestos a mano: los N primeros de todos los grupos y, si se usan, los
     * mejores de una posición.
     *
     * @param  list<string>  $codigos
     * @param  list<string>  $letras
     * @return array{por_grupo: int, cuantos_mejores: int, mejores_de_posicion: int|null}
     *
     * @throws ConfiguracionInvalida
     */
    public static function quienPasaDesde(array $codigos, array $letras, ?int $mejoresDe): array
    {
        $directos = [];
        $mejores = [];
        $errores = [];

        foreach ($codigos as $codigo) {
            if (preg_match('/^M(\d+)$/', $codigo, $partes) === 1) {
                $mejores[] = (int) $partes[1];
            } elseif (preg_match('/^(\d+)([A-Z]+)$/', $codigo, $partes) === 1) {
                if (! in_array($partes[2], $letras, true)) {
                    $errores[] = "No hay grupo {$partes[2]}.";
                }
                $directos[(int) $partes[1]][$partes[2]] = true;
            }
        }

        $porGrupo = $directos === [] ? 0 : max(array_keys($directos));
        for ($posicion = 1; $posicion <= $porGrupo; $posicion++) {
            foreach ($letras as $letra) {
                if (! isset($directos[$posicion][$letra])) {
                    $errores[] = "Falta el {$posicion}º del grupo {$letra}: si pasan los {$posicion}º de un grupo, pasan los de todos (los sueltos van como «mejores»).";
                }
            }
        }

        sort($mejores);
        if ($mejores !== [] && $mejores !== range(1, count($mejores))) {
            $errores[] = 'Los mejores van por orden: Mejor nº 1, nº 2…';
        }
        if ($mejores !== [] && ($mejoresDe === null || $mejoresDe <= $porGrupo)) {
            $errores[] = 'Los «mejores» tienen que ser de una posición que no pase entera: de los '.($porGrupo + 1).'º o más.';
        }

        if ($errores !== []) {
            throw new ConfiguracionInvalida(array_values(array_unique($errores)));
        }

        return ['por_grupo' => $porGrupo, 'cuantos_mejores' => count($mejores), 'mejores_de_posicion' => $mejores === [] ? null : $mejoresDe];
    }

    /**
     * Las posiciones de los cruces a mano, en orden: primero todos los
     * primeros (A, B…), luego los segundos… y al final los mejores.
     *
     * @param  array<mixed>  $filas
     * @return list<string>
     */
    public static function codigosDeLosCruces(array $filas): array
    {
        $codigos = [];
        foreach ($filas as $fila) {
            foreach (array_values((array) $fila) as $codigo) {
                if (is_string($codigo) && $codigo !== '') {
                    $codigos[] = strtoupper($codigo);
                }
            }
        }

        $clave = function (string $codigo): array {
            if (preg_match('/^M(\d+)$/', $codigo, $partes) === 1) {
                return [1000, (int) $partes[1], ''];
            }
            preg_match('/^(\d+)([A-Z]+)$/', $codigo, $partes);

            return [(int) ($partes[1] ?? 999), 0, str_pad($partes[2] ?? '', 3, ' ', STR_PAD_LEFT)];
        };
        usort($codigos, fn (string $a, string $b): int => $clave($a) <=> $clave($b));

        return array_values(array_unique($codigos));
    }

    /**
     * El esquema del cuadro con lo que hay en el formulario: rondas,
     * cruces y quién llega a cada hueco. Sin fase de grupos antes, con los
     * cabezas de serie de las parejas confirmadas.
     */
    /** @param  array<mixed>  $datos  La configuración del formulario. */
    public static function esquema(array $datos, RelationManager $livewire, ?Fase $fase): HtmlString
    {
        $posiciones = self::posiciones($livewire, $fase);
        $codigos = array_keys($posiciones);
        // «Mejor 3º nº 1»: de qué posición son los mejores.
        $mejoresDe = preg_match('/^Mejor (\d+)º/', $posiciones['M1'] ?? '', $partes) === 1 ? (int) $partes[1] : null;
        $aviso = null;

        if ($codigos === []) {
            $parejas = self::categoria($livewire)->participantes()->where('estado', EstadoParticipante::Activo->value)
                ->whereHas('inscripcion', fn ($q) => $q->where('estado', EstadoInscripcion::Confirmada->value))->count();
            $codigos = array_map(fn (int $i): string => 'CS'.$i, range(1, max(2, $parejas)));
            $aviso = 'Sin fase de grupos antes: con las '.$parejas.' parejas confirmadas, por orden de cabeza de serie.';
        } elseif (($datos['regla_de_cruces'] ?? null) === ReglaDeCruces::EntreGrupos->value) {
            $aviso = 'Orientativo: entre los primeros (y entre los segundos) el orden sale de sus resultados en los grupos.';
        }

        if (($datos['regla_de_cruces'] ?? null) === ReglaDeCruces::APosiciones->value) {
            // A mano: pasan justo los que estén en los cruces.
            $usados = self::codigosDeLosCruces((array) ($datos['cruces_por_posicion'] ?? []));
            $grupos = self::faseDeGrupos(self::categoria($livewire), $fase);
            $mejoresDe = is_numeric($datos['mejores_de'] ?? null) ? (int) $datos['mejores_de'] : self::mejoresDeAhora($livewire, $fase);

            if ($usados === [] || $grupos === null) {
                $esquema = ['rondas' => [], 'tercero' => null, 'error' => $grupos === null ? 'Añade antes la fase de grupos de la que salen las parejas.' : 'Añade los cruces de la primera ronda (o pulsa «Partir del cuadro automático») para ver el cuadro.'];
            } else {
                try {
                    self::quienPasaDesde($usados, self::letrasYTamano(self::categoria($livewire), $grupos)[0], $mejoresDe);
                    $esquema = app(EsquemaDeCuadro::class)->armar($usados, ConfiguracionDeEliminatoria::desdeArray($datos), $mejoresDe);
                } catch (ConfiguracionInvalida $e) {
                    $esquema = ['rondas' => [], 'tercero' => null, 'error' => implode(' ', $e->errores)];
                }
            }
            $aviso = null;
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
        $grupos = self::faseDeGrupos($categoria, $fase);

        if ($grupos === null) {
            return [];
        }

        try {
            $configuracion = $grupos->configuracionDeGrupos();
        } catch (ConfiguracionInvalida) {
            return [];
        }

        [$letras] = self::letrasYTamano($categoria, $grupos);

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
