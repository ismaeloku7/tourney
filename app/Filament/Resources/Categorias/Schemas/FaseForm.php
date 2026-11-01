<?php

namespace App\Filament\Resources\Categorias\Schemas;

use App\Competicion\Datos\ConfiguracionDeEliminatoria;
use App\Competicion\Datos\ConfiguracionDeGrupos;
use App\Competicion\Errores\ConfiguracionInvalida;
use App\Enums\CriterioDeDesempate;
use App\Enums\ModoDeSorteo;
use App\Enums\OrigenDeConsolacion;
use App\Enums\ReglaDeCruces;
use App\Enums\TipoDeFase;
use App\Models\Categoria;
use App\Models\Fase;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

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
                        ->options([ModoDeSorteo::Aleatorio->value => ModoDeSorteo::Aleatorio->etiqueta(), ModoDeSorteo::CabezasDeSerie->value => ModoDeSorteo::CabezasDeSerie->etiqueta()])
                        ->default(ModoDeSorteo::Aleatorio->value)->required()
                        ->visible(fn (Get $get): bool => self::tipo($get('tipo')) === TipoDeFase::Grupos)
                        ->helperText('Con cabezas de serie, cada una encabeza un grupo. El reparto manual se hace al sortear.'),
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
                    Select::make('configuracion.tamano_de_cuadro')->label('Tamaño del cuadro')
                        ->options([2 => '2 (final)', 4 => '4', 8 => '8', 16 => '16', 32 => '32', 64 => '64', 128 => '128'])
                        ->placeholder('El mínimo en el que caben'),
                    Select::make('configuracion.regla_de_cruces')->label('Cruces')
                        ->options([ReglaDeCruces::EntreGrupos->value => ReglaDeCruces::EntreGrupos->etiqueta(), ReglaDeCruces::Sorteo->value => ReglaDeCruces::Sorteo->etiqueta()])
                        ->default(ReglaDeCruces::EntreGrupos->value)->required()
                        ->helperText('Los cruces manuales se fijan al generar el cuadro.'),
                    Toggle::make('configuracion.evitar_reencuentros')->label('Evitar reencuentros de la fase anterior')->default(true)->inline(false),
                    Toggle::make('configuracion.tercer_y_cuarto_puesto')->label('Partido por el tercer puesto')->default(false)->inline(false),
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
