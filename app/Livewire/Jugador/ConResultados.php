<?php

namespace App\Livewire\Jugador;

use App\Actions\Competicion\RegistrarResultado;
use App\Actions\Competicion\ValidarResultado;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Competicion\Errores\ResultadoInvalido;
use App\Enums\EstadoPartido;
use App\Jugadores\PartidosDelJugador;
use App\Livewire\Concerns\FormularioDeResultado;
use App\Models\Partido;
use Illuminate\Database\Eloquent\Builder;

/**
 * Anotar y validar resultados desde el portal, en las páginas que
 * enseñan partidos. Solo sobre los partidos del propio jugador.
 */
trait ConResultados
{
    use FormularioDeResultado;

    public function guardarResultado(RegistrarResultado $registrar): void
    {
        $partido = $this->misPartidos()->whereKey((int) $this->editando)->first();
        abort_if($partido === null, 404);

        try {
            $guardado = $registrar->handle($partido, $this->resultadoDelFormulario(), $this->yo());
        } catch (ResultadoInvalido $e) {
            $this->addError('resultado', implode(' ', $e->errores));

            return;
        } catch (ErrorDeCompeticion $e) {
            $this->addError('resultado', $e->getMessage());

            return;
        }

        $this->editando = null;
        $this->aviso = $guardado->estado === EstadoPartido::PendienteValidacion
            ? 'Resultado anotado. Falta que lo valide la pareja rival.'
            : 'Resultado anotado.';
    }

    public function validar(int $partido, ValidarResultado $validar): void
    {
        $encontrado = $this->misPartidos()->whereKey($partido)->first();
        abort_if($encontrado === null, 404);

        $this->accion(fn () => $validar->handle($encontrado, $this->yo()), 'Resultado validado.');
    }

    /** @return Builder<Partido> */
    protected function misPartidos(): Builder
    {
        return app(PartidosDelJugador::class)->consulta($this->yo());
    }
}
