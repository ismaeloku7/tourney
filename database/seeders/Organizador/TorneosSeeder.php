<?php

namespace Database\Seeders\Organizador;

use App\Actions\Competicion\CerrarFase;
use App\Actions\Competicion\GenerarFranjas;
use App\Actions\Competicion\GenerarPartidos;
use App\Actions\Competicion\PlanificarHorarios;
use App\Actions\Competicion\RegistrarResultado;
use App\Actions\Competicion\SortearGrupos;
use App\Competicion\Datos\Resultado;
use App\Competicion\Datos\Set;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoPartido;
use App\Enums\EstadoTorneo;
use App\Enums\Genero;
use App\Enums\OrigenInscripcion;
use App\Enums\Rol;
use App\Models\Categoria;
use App\Models\Fase;
use App\Models\FormatoPartido;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Participante;
use App\Models\Partido;
use App\Models\Pista;
use App\Models\Torneo;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

/**
 * Un torneo por estado, identificado por su slug (repetir no duplica):
 *
 * - `open-demo-sorteo`: inscripción cerrada, dos categorías de 16 parejas
 *   en grupos de 4 con 2 partidos por pareja, sorteado y planificado.
 * - `open-demo-en-juego`: una categoría de 12 parejas en 3 grupos, con el
 *   grupo A entero y la mitad de los demás resultados.
 * - `open-demo-finalizado`: cuadro de 8 con dos mejores terceros y
 *   consolación, todo jugado.
 */
class TorneosSeeder extends Seeder
{
    private User $propietario;

    public function run(): void
    {
        $this->propietario = User::query()->where('rol', Rol::Propietario->value)->firstOrFail();

        $this->torneoEnSorteo();
        $this->torneoEnJuego();
        $this->torneoFinalizado();
    }

    private function torneoEnSorteo(): void
    {
        if (Torneo::query()->where('slug', 'open-demo-sorteo')->exists()) {
            return;
        }

        $torneo = $this->torneo('Open Demo de Primavera', 'open-demo-sorteo', EstadoTorneo::Sorteo, now()->addDays(14));

        foreach ([['Masculina 3ª', Genero::Masculino], ['Femenina 4ª', Genero::Femenino]] as $i => [$nombre, $genero]) {
            $categoria = $this->categoria($torneo, $nombre, $genero, $i + 1, 16);
            $fase = $this->faseDeGrupos($categoria, ['tamano_de_grupo' => 4, 'partidos_por_participante' => 2, 'semilla' => 100 + $i]);
            $this->faseDeCuadro($categoria);

            app(SortearGrupos::class)->handle($fase, $this->propietario);
            app(GenerarPartidos::class)->handle($fase, $this->propietario);
        }

        app(GenerarFranjas::class)->handle($torneo);
        app(PlanificarHorarios::class)->handle($torneo, $this->propietario);
    }

    private function torneoEnJuego(): void
    {
        if (Torneo::query()->where('slug', 'open-demo-en-juego')->exists()) {
            return;
        }

        $torneo = $this->torneo('Open Demo de Otoño', 'open-demo-en-juego', EstadoTorneo::EnJuego, now()->subDay());
        $categoria = $this->categoria($torneo, 'Mixta B', Genero::Mixto, 1, 12);
        $fase = $this->faseDeGrupos($categoria, ['tamano_de_grupo' => 4, 'semilla' => 200]);
        $this->faseDeCuadro($categoria);

        app(SortearGrupos::class)->handle($fase, $this->propietario);
        app(GenerarPartidos::class)->handle($fase, $this->propietario);
        app(GenerarFranjas::class)->handle($torneo);
        app(PlanificarHorarios::class)->handle($torneo, $this->propietario);

        // El grupo A entero, con ganadores alternos; del resto, la mitad.
        foreach ($fase->grupos()->get() as $indice => $grupo) {
            $partidos = $grupo->partidos()->orderBy('jornada')->orderBy('id')->get();
            $cuantos = $indice === 0 ? $partidos->count() : intdiv($partidos->count(), 2);

            foreach ($partidos->take($cuantos)->values() as $n => $partido) {
                $this->registrar($partido, $n % 2 === 0);
            }
        }
    }

    private function torneoFinalizado(): void
    {
        if (Torneo::query()->where('slug', 'open-demo-finalizado')->exists()) {
            return;
        }

        $torneo = $this->torneo('Open Demo de Invierno', 'open-demo-finalizado', EstadoTorneo::Finalizado, now()->subDays(30));
        $categoria = $this->categoria($torneo, 'Masculina 4ª', Genero::Masculino, 1, 12);
        $grupos = $this->faseDeGrupos($categoria, ['tamano_de_grupo' => 4, 'semilla' => 300, 'clasificados' => ['por_grupo' => 2, 'mejores_de_posicion' => 3, 'cuantos_mejores' => 2]]);
        $cuadro = $this->faseDeCuadro($categoria, ['consolacion' => 'no_clasificados', 'tercer_y_cuarto_puesto' => true]);
        $consolacion = Fase::factory()->consolacion()->create(['categoria_id' => $categoria->id, 'nombre' => 'Consolación']);

        app(SortearGrupos::class)->handle($grupos, $this->propietario);
        app(GenerarPartidos::class)->handle($grupos, $this->propietario);
        app(GenerarFranjas::class)->handle($torneo);
        app(PlanificarHorarios::class)->handle($torneo, $this->propietario);

        $this->jugarTodo($grupos);
        app(CerrarFase::class)->handle($grupos, $this->propietario);

        foreach ([$cuadro, $consolacion] as $fase) {
            app(PlanificarHorarios::class)->handle($torneo, $this->propietario);
            $this->jugarTodo($fase);
            app(CerrarFase::class)->handle($fase, $this->propietario);
        }

        $torneo->fill(['finalizado_en' => now()->subDays(29)])->save();
    }

    private function torneo(string $nombre, string $slug, EstadoTorneo $estado, CarbonInterface $inicio): Torneo
    {
        $torneo = Torneo::factory()->enEstado($estado)->create([
            'nombre' => $nombre,
            'slug' => $slug,
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $inicio->copy()->addDay()->toDateString(),
            'inscripcion_desde' => $inicio->copy()->subDays(30),
            'inscripcion_hasta' => $inicio->copy()->subDays(2),
            'creado_por' => $this->propietario->id,
            'horarios_publicados_en' => $estado === EstadoTorneo::Sorteo ? null : $inicio->copy()->subDay(),
        ]);

        foreach (Pista::query()->orderBy('orden')->get() as $pista) {
            $torneo->torneoPistas()->create([
                'pista_id' => $pista->id,
                'orden' => $pista->orden,
                'disponibilidad' => [
                    ['fecha' => $inicio->toDateString(), 'desde' => '09:00', 'hasta' => '22:00'],
                    ['fecha' => $inicio->copy()->addDay()->toDateString(), 'desde' => '09:00', 'hasta' => '22:00'],
                ],
            ]);
        }

        return $torneo;
    }

    private function categoria(Torneo $torneo, string $nombre, Genero $genero, int $orden, int $parejas): Categoria
    {
        $categoria = Categoria::factory()->create([
            'torneo_id' => $torneo->id,
            'nombre' => $nombre,
            'genero' => $genero,
            'orden' => $orden,
            'cupo' => $parejas,
            'formato_partido_id' => FormatoPartido::query()->where('nombre', 'Un set a 9')->value('id'),
        ]);

        for ($i = 1; $i <= $parejas; $i++) {
            $jugadores = $i === 1 && $orden === 1
                ? [Jugador::query()->where('email', 'jugador@example.com')->firstOrFail(), Jugador::factory()->create(['genero' => $genero === Genero::Femenino ? Genero::Femenino : Genero::Masculino])]
                : Jugador::factory()->count(2)->create()->all();

            $participante = Participante::factory()->conJugadores(...$jugadores)->create([
                'categoria_id' => $categoria->id,
                'cabeza_de_serie' => $i <= 4 ? $i : null,
            ]);

            Inscripcion::factory()->create([
                'participante_id' => $participante->id,
                'estado' => EstadoInscripcion::Confirmada,
                'origen' => $i % 3 === 0 ? OrigenInscripcion::Manual : OrigenInscripcion::Online,
                'registrada_por' => $this->propietario->id,
            ]);
        }

        return $categoria;
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function faseDeGrupos(Categoria $categoria, array $cambios): Fase
    {
        return Fase::factory()->grupos(array_merge(['modo_de_sorteo' => 'cabezas_de_serie'], $cambios))->create(['categoria_id' => $categoria->id]);
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function faseDeCuadro(Categoria $categoria, array $cambios = []): Fase
    {
        return Fase::factory()->eliminatoria($cambios)->create(['categoria_id' => $categoria->id]);
    }

    private function jugarTodo(Fase $fase): void
    {
        // Mientras queden partidos con los dos participantes y sin cerrar
        // (los cuadros se van rellenando al avanzar).
        for ($vuelta = 0; $vuelta < 10; $vuelta++) {
            $pendientes = $fase->partidos()->whereNotNull('local_id')->whereNotNull('visitante_id')->whereIn('estado', [EstadoPartido::Pendiente->value, EstadoPartido::Programado->value])->orderBy('id')->get();

            if ($pendientes->isEmpty()) {
                return;
            }

            foreach ($pendientes->values() as $n => $partido) {
                $this->registrar($partido, $n % 3 !== 2);
            }
        }
    }

    /** Las categorías de los seeders juegan a un set a 9: 9-6 para quien gane. */
    private function registrar(Partido $partido, bool $ganaLocal): void
    {
        $resultado = new Resultado([$ganaLocal ? new Set(9, 6) : new Set(6, 9)]);
        app(RegistrarResultado::class)->handle($partido->refresh(), $resultado, $this->propietario);
    }
}
