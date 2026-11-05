<?php

namespace App\Livewire\Concerns;

use App\Actions\Competicion\AnotarMarcador;
use App\Actions\Competicion\EmpezarPartido;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\User;

/**
 * Llevar un partido en vivo desde el móvil, en la mesa y en el portal:
 * empezarlo, sumar o quitar juegos, cerrar un set y terminarlo abriendo
 * el formulario de resultado con los sets ya puestos. Quien lo use da el
 * partido permitido y quién es (`partidoEnVivo()` y `quienMarca()`).
 */
trait MarcadorEnVivo
{
    use FormularioDeResultado;

    abstract protected function partidoEnVivo(int $id): Partido;

    abstract protected function quienMarca(): User|Jugador;

    public function empezarPartido(int $partido, EmpezarPartido $empezar): void
    {
        $this->enVivo(fn () => $empezar->handle($this->partidoEnVivo($partido), $this->quienMarca()), 'Partido en pista.');
    }

    public function juego(int $partido, bool $local, AnotarMarcador $anotar): void
    {
        $this->cambiarMarcador($partido, function (array $sets, array $juegos) use ($local): array {
            $juegos[$local ? 0 : 1]++;

            return [$sets, $juegos];
        }, $anotar);
    }

    public function quitarJuego(int $partido, bool $local, AnotarMarcador $anotar): void
    {
        $this->cambiarMarcador($partido, function (array $sets, array $juegos) use ($local): array {
            $juegos[$local ? 0 : 1] = max(0, $juegos[$local ? 0 : 1] - 1);

            return [$sets, $juegos];
        }, $anotar);
    }

    public function cerrarSet(int $partido, AnotarMarcador $anotar): void
    {
        $this->cambiarMarcador($partido, function (array $sets, array $juegos): array {
            if ($juegos[0] === 0 && $juegos[1] === 0) {
                return [$sets, $juegos];
            }
            $sets[] = $juegos;

            return [$sets, [0, 0]];
        }, $anotar);
    }

    public function reabrirSet(int $partido, AnotarMarcador $anotar): void
    {
        $this->cambiarMarcador($partido, function (array $sets, array $juegos): array {
            if ($sets === [] || $juegos[0] > 0 || $juegos[1] > 0) {
                return [$sets, $juegos];
            }
            $ultimo = array_pop($sets);

            return [$sets, $ultimo];
        }, $anotar);
    }

    /** Abre el formulario de resultado con lo que marca el partido: solo queda confirmar. */
    public function terminarPartido(int $partido): void
    {
        $encontrado = $this->partidoEnVivo($partido);
        $this->abrirResultado($partido);

        $marcador = $encontrado->marcador_en_vivo ?? ['sets' => [], 'juegos' => [0, 0]];
        $sets = $marcador['sets'];
        if ($marcador['juegos'][0] > 0 || $marcador['juegos'][1] > 0) {
            $sets[] = $marcador['juegos'];
        }

        foreach (array_slice($sets, 0, 3) as $i => $set) {
            $this->sets[$i]['juegos_local'] = $set[0];
            $this->sets[$i]['juegos_visitante'] = $set[1];
        }
    }

    /**
     * @param  callable(list<array{0: int, 1: int}>, array{0: int, 1: int}): array{0: list<array{0: int, 1: int}>, 1: array{0: int, 1: int}}  $cambio
     */
    private function cambiarMarcador(int $partido, callable $cambio, AnotarMarcador $anotar): void
    {
        $encontrado = $this->partidoEnVivo($partido);
        $marcador = $encontrado->marcador_en_vivo ?? ['sets' => [], 'juegos' => [0, 0]];
        [$sets, $juegos] = $cambio($marcador['sets'], $marcador['juegos']);

        $this->enVivo(fn () => $anotar->handle($encontrado, $sets, $juegos, $this->quienMarca()), null);
    }

    private function enVivo(callable $hacer, ?string $hecho): void
    {
        try {
            $hacer();
        } catch (ErrorDeCompeticion $e) {
            $this->addError('portal', $e->getMessage());

            return;
        }

        $this->resetErrorBag();
        if ($hecho !== null) {
            $this->aviso = $hecho;
        }
    }
}
