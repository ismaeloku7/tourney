<?php

namespace App\Console\Commands;

use App\Enums\EstadoOrganizador;
use App\Enums\EstadoPartido;
use App\Models\Jugador;
use App\Models\Organizador;
use App\Models\Partido;
use App\Notifications\ProximoPartido;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

/**
 * «Juegas en media hora»: a los jugadores de los partidos con hora que
 * empiezan dentro de 30 minutos (o menos, si se perdió una vuelta). Una
 * sola vez por partido. Recorre los organizadores activos; cada minuto.
 */
class AvisarProximosPartidos extends Command
{
    public const MINUTOS = 30;

    protected $signature = 'avisos:proximos-partidos';

    protected $description = 'Avisa por push a los jugadores de los partidos que empiezan en media hora';

    public function handle(): int
    {
        $total = 0;

        foreach (Organizador::query()->where('estado', EstadoOrganizador::Activo->value)->get() as $organizador) {
            assert($organizador instanceof Organizador);
            $total += (int) $organizador->run(function (): int {
                $avisados = 0;

                $partidos = Partido::query()
                    ->whereNull('aviso_proximo_en')
                    ->whereIn('estado', [EstadoPartido::Programado->value, EstadoPartido::Pendiente->value])
                    ->whereNotNull('local_id')->whereNotNull('visitante_id')
                    ->whereHas('franja', fn (Builder $q) => $q->where('inicio', '>', now())->where('inicio', '<=', now()->addMinutes(self::MINUTOS)))
                    ->whereHas('fase.categoria.torneo', fn (Builder $q) => $q->whereNotNull('horarios_publicados_en'))
                    ->with(['local.jugadores', 'visitante.jugadores', 'franja', 'pista'])
                    ->get();

                foreach ($partidos as $partido) {
                    $partido->forceFill(['aviso_proximo_en' => now()])->save();
                    $jugadores = [...($partido->local?->jugadores->all() ?? []), ...($partido->visitante?->jugadores->all() ?? [])];
                    Notification::send(array_values(array_filter($jugadores, fn (Jugador $j): bool => $j->anonimizado_en === null)), new ProximoPartido($partido));
                    $avisados++;
                }

                return $avisados;
            });
        }

        $this->info("Partidos avisados: {$total}.");

        return self::SUCCESS;
    }
}
