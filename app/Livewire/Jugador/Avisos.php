<?php

namespace App\Livewire\Jugador;

use App\Models\SuscripcionPush;
use App\Push\Push;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;

/** Por dónde quiere el jugador sus avisos (correo, push), la app en el móvil y el historial. */
#[Layout('components.layouts.jugador')]
class Avisos extends PaginaDelJugador
{
    public bool $avisosCorreo = true;

    public bool $avisosPush = true;

    public function mount(): void
    {
        $yo = $this->yo();
        $this->avisosCorreo = (bool) ($yo->avisos['correo'] ?? true);
        $this->avisosPush = (bool) ($yo->avisos['push'] ?? true);
    }

    public function guardarAvisos(): void
    {
        $this->yo()->update(['avisos' => ['correo' => $this->avisosCorreo, 'push' => $this->avisosPush]]);
        $this->aviso = 'Avisos guardados.';
    }

    /** @param  array<string, mixed>  $suscripcion */
    public function guardarPush(array $suscripcion, ?string $dispositivo = null): void
    {
        try {
            app(Push::class)->guardar($suscripcion, $this->yo(), $dispositivo);
        } catch (InvalidArgumentException) {
            $this->addError('portal', 'No se han podido activar las notificaciones en este dispositivo.');
        }
    }

    public function quitarPush(string $endpoint): void
    {
        SuscripcionPush::query()->where('endpoint_hash', hash('sha256', $endpoint))->where('jugador_id', $this->yo()->id)->delete();
    }

    public function render(): View
    {
        return view('livewire.jugador.avisos', [
            'historial' => $this->yo()->notifications()->latest()->limit(20)->get(),
        ])->title('Avisos');
    }
}
