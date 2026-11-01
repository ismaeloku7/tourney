<?php

namespace App\Livewire\Publico;

use App\Enums\EstadoTorneo;
use App\Models\Jugador;
use App\Models\SuscripcionPush;
use App\Models\Torneo;
use App\Publico\DatosDelTorneo;
use App\Publico\TorneosPublicos;
use App\Push\Push;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * La ficha pública del torneo: datos y categorías con plazas, y las
 * pestañas de inscritos, grupos, cuadro y horarios. Mientras el torneo
 * está en juego se refresca sola cada 15 segundos.
 */
#[Layout('components.layouts.publico')]
class FichaDelTorneo extends Component
{
    public const PESTANAS = ['info' => 'Torneo', 'inscritos' => 'Inscritos', 'grupos' => 'Grupos', 'cuadro' => 'Cuadro', 'horarios' => 'Horarios'];

    public Torneo $torneo;

    #[Url(as: 'ver')]
    public string $pestana = 'info';

    public function mount(Torneo $torneo): void
    {
        abort_unless(TorneosPublicos::esPublico($torneo), 404);

        $this->torneo = $torneo;

        if (! array_key_exists($this->pestana, self::PESTANAS)) {
            $this->pestana = 'info';
        }
    }

    public function ver(string $pestana): void
    {
        if (array_key_exists($pestana, self::PESTANAS)) {
            $this->pestana = $pestana;
        }
    }

    /**
     * Seguir el torneo desde este dispositivo, con cuenta o sin ella:
     * horarios y resultados por push.
     *
     * @param  array<string, mixed>  $suscripcion
     */
    public function guardarPush(array $suscripcion, ?string $dispositivo = null): void
    {
        try {
            $guardada = app(Push::class)->guardar($suscripcion, $this->jugador(), $dispositivo);
        } catch (InvalidArgumentException) {
            return;
        }

        $guardada->torneos()->syncWithoutDetaching([$this->torneo->id]);
    }

    /** Deja de seguir el torneo; el dispositivo sigue recibiendo lo demás si es de un jugador. */
    public function quitarPush(string $endpoint): void
    {
        $suscripcion = SuscripcionPush::query()->where('endpoint_hash', hash('sha256', $endpoint))->first();
        $suscripcion?->torneos()->detach($this->torneo->id);
    }

    public function sigue(string $endpoint): bool
    {
        return SuscripcionPush::query()->where('endpoint_hash', hash('sha256', $endpoint))
            ->whereHas('torneos', fn ($q) => $q->whereKey($this->torneo->id))->exists();
    }

    private function jugador(): ?Jugador
    {
        $jugador = Auth::guard('jugador')->user();

        return $jugador instanceof Jugador ? $jugador : null;
    }

    public function render(): View
    {
        $datos = new DatosDelTorneo($this->torneo->refresh()->load(['categorias', 'sede']));

        return view('livewire.publico.ficha-del-torneo', [
            'datos' => $datos,
            'enJuego' => $this->torneo->estado === EstadoTorneo::EnJuego,
            'contenido' => match ($this->pestana) {
                'inscritos' => $datos->inscritos(),
                'grupos' => $datos->grupos(),
                'cuadro' => $datos->cuadros(),
                'horarios' => $datos->horarios(),
                default => null,
            },
        ]);
    }
}
