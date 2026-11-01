<?php

namespace App\Actions\Competicion;

use App\Avisos\Avisos;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoTorneo;
use App\Models\Partido;
use App\Models\RegistroDeCambio;
use App\Models\Torneo;
use App\Models\User;

/**
 * Las transiciones del ciclo de vida del torneo, de una en una y con sus
 * comprobaciones: no se publica sin categorías y no se pasa a en juego
 * sin horarios.
 */
final class CambiarEstadoDelTorneo
{
    public function handle(Torneo $torneo, EstadoTorneo $estado, User $usuario): Torneo
    {
        $actual = $torneo->estado;

        if ($estado !== $actual->siguiente() && $estado !== $actual->anterior()) {
            throw new OperacionNoPermitida('De «'.$actual->etiqueta().'» no se pasa a «'.$estado->etiqueta().'».');
        }

        if ($estado === EstadoTorneo::InscripcionAbierta && $torneo->categorias()->doesntExist()) {
            throw new OperacionNoPermitida('No se abre la inscripción de un torneo sin categorías.');
        }

        if ($estado === EstadoTorneo::EnJuego && ! $this->tieneHorarios($torneo)) {
            throw new OperacionNoPermitida('No se pasa a en juego sin horarios: planifique los partidos antes.');
        }

        $sinHorariosPublicados = $torneo->horarios_publicados_en === null;
        $torneo->estado = $estado;

        if ($estado->publicado() && $torneo->publicado_en === null) {
            $torneo->publicado_en = now();
        }
        if ($estado === EstadoTorneo::EnJuego && $torneo->horarios_publicados_en === null) {
            $torneo->horarios_publicados_en = now();
        }
        if ($estado === EstadoTorneo::Finalizado) {
            $torneo->finalizado_en = now();
        }
        if ($estado === EstadoTorneo::Archivado) {
            $torneo->archivado_en = now();
        }

        $torneo->save();

        RegistroDeCambio::anotar('cambiar_estado', $torneo, $usuario, ['estado' => $actual->value], ['estado' => $estado->value]);

        // Al publicar los horarios por primera vez, a cada jugador los suyos.
        if ($estado === EstadoTorneo::EnJuego && $sinHorariosPublicados) {
            app(Avisos::class)->horariosPublicados($torneo);
        }

        return $torneo;
    }

    private function tieneHorarios(Torneo $torneo): bool
    {
        return Partido::query()
            ->whereHas('fase.categoria', fn ($consulta) => $consulta->where('torneo_id', $torneo->id))
            ->whereNotNull('franja_id')
            ->exists();
    }
}
