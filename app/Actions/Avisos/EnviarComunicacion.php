<?php

namespace App\Actions\Avisos;

use App\Avisos\Avisos;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Models\Categoria;
use App\Models\Comunicacion;
use App\Models\Torneo;
use App\Models\User;

/** Un aviso libre de la organización a los inscritos del torneo o de una categoría. */
final class EnviarComunicacion
{
    public function __construct(private readonly Avisos $avisos) {}

    /**
     * @param  list<string>  $canales  «correo», «push» o los dos
     *
     * @throws OperacionNoPermitida
     */
    public function handle(Torneo $torneo, ?Categoria $categoria, string $titulo, string $cuerpo, array $canales, User $usuario): Comunicacion
    {
        $canales = array_values(array_intersect(['correo', 'push'], $canales));

        if ($canales === []) {
            throw new OperacionNoPermitida('Elige al menos un canal: correo o push.');
        }

        if ($categoria !== null && $categoria->torneo_id !== $torneo->id) {
            throw new OperacionNoPermitida('La categoría no es de este torneo.');
        }

        $comunicacion = Comunicacion::query()->create([
            'torneo_id' => $torneo->id,
            'categoria_id' => $categoria?->id,
            'titulo' => trim($titulo),
            'cuerpo' => trim($cuerpo),
            'canales' => $canales,
            'enviada_por' => $usuario->id,
        ]);

        $destinatarios = $this->avisos->comunicacion($comunicacion);
        $comunicacion->update(['enviada_en' => now(), 'destinatarios' => $destinatarios]);

        return $comunicacion;
    }
}
