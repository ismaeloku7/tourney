<?php

namespace App\Livewire\Jugador;

use App\Actions\Jugadores\AnonimizarJugador;
use App\Actions\Jugadores\CambiarNivel;
use App\Competicion\Errores\ErrorDeCompeticion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;

/** Los datos del jugador (nombre, teléfono, alias, nivel y el de sus compañeros) y el borrado de su cuenta. */
#[Layout('components.layouts.jugador')]
class MisDatos extends PaginaDelJugador
{
    public string $nombre = '';

    public string $apellidos = '';

    public string $telefono = '';

    public string $alias = '';

    public string $nivel = '';

    /** @var array<int, string> compañero (jugador) => nivel, de las parejas con torneo sin terminar */
    public array $companeros = [];

    public string $confirmarBorrado = '';

    public function mount(): void
    {
        $yo = $this->yo();
        $this->nombre = $yo->nombre;
        $this->apellidos = $yo->apellidos;
        $this->telefono = (string) $yo->getAttribute('telefono');
        $this->alias = (string) $yo->alias;
        $this->nivel = (string) $yo->nivel_declarado;
        foreach (CambiarNivel::companeros($yo) as $companero) {
            $this->companeros[$companero->id] = (string) $companero->nivel_declarado;
        }
    }

    public function guardarDatos(): void
    {
        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:80'],
            'apellidos' => ['required', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'alias' => ['nullable', 'string', 'max:60'],
        ]);

        // El nivel solo se comprueba si lo cambia: uno antiguo («4ª») no le impide guardar lo demás.
        if (trim($this->nivel) !== (string) $this->yo()->nivel_declarado) {
            try {
                app(CambiarNivel::class)->handle($this->yo(), $this->yo(), $this->nivel);
            } catch (ErrorDeCompeticion $e) {
                $this->addError('nivel', $e->getMessage());

                return;
            }
        }

        // El de cada compañero, igual: solo si cambia.
        foreach (CambiarNivel::companeros($this->yo()) as $companero) {
            $nuevo = trim($this->companeros[$companero->id] ?? '');
            if ($nuevo === (string) $companero->nivel_declarado) {
                continue;
            }
            try {
                app(CambiarNivel::class)->handle($this->yo(), $companero, $nuevo);
            } catch (ErrorDeCompeticion $e) {
                $this->addError('companeros.'.$companero->id, $e->getMessage());

                return;
            }
        }

        $this->yo()->update(array_map(fn (mixed $v): ?string => is_string($v) && trim($v) !== '' ? trim($v) : null, $datos));
        $this->aviso = 'Datos guardados.';
    }

    public function borrarMisDatos(AnonimizarJugador $anonimizar): void
    {
        if (mb_strtoupper(trim($this->confirmarBorrado)) !== 'BORRAR') {
            $this->addError('confirmarBorrado', 'Escribe BORRAR para confirmar.');

            return;
        }

        $anonimizar->handle($this->yo());
        Auth::guard('jugador')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect('/');
    }

    public function render(): View
    {
        return view('livewire.jugador.mis-datos', ['yo' => $this->yo(), 'listaDeCompaneros' => CambiarNivel::companeros($this->yo())])->title('Mis datos');
    }
}
