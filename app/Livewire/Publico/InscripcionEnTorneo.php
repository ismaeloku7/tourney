<?php

namespace App\Livewire\Publico;

use App\Actions\Jugadores\InscribirseOnline;
use App\Actions\Jugadores\MandarEnlaceDeAcceso;
use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\Ajuste;
use App\Enums\EstadoInscripcion;
use App\Http\Controllers\AccesoDelJugador;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Torneo;
use App\Models\ValorDeAjuste;
use App\Publico\TorneosPublicos;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * La inscripción online en dos pasos. Primero, quién eres: si tu correo ya
 * es de un jugador te mandamos el enlace para entrar; si no, se crea tu
 * cuenta ligera. Después, categoría y compañero, que recibe la invitación.
 */
#[Layout('components.layouts.publico')]
class InscripcionEnTorneo extends Component
{
    private const ENVIOS_POR_HORA = 10;

    public Torneo $torneo;

    // Paso 1: quién eres.
    public string $nombre = '';

    public string $apellidos = '';

    public string $email = '';

    public string $telefono = '';

    public bool $privacidad = false;

    public bool $condiciones = false;

    /** Campo trampa: invisible para una persona. */
    public string $sitio_web = '';

    public bool $enlaceEnviado = false;

    // Paso 2: categoría y compañero.
    public ?int $categoria_id = null;

    public string $companero_email = '';

    public string $companero_nombre = '';

    public string $companero_apellidos = '';

    public string $companero_telefono = '';

    public string $notas = '';

    public ?int $hecha = null;

    public function mount(Torneo $torneo): void
    {
        abort_unless(TorneosPublicos::esPublico($torneo), 404);

        $this->torneo = $torneo;
    }

    /** Paso 1. */
    public function identificarme(MandarEnlaceDeAcceso $mandar): void
    {
        // Sin inscripción online, la web no crea cuentas ni manda enlaces desde aquí.
        try {
            InscribirseOnline::comprobarQueEstaAbierta($this->torneo);
        } catch (OperacionNoPermitida $e) {
            $this->addError('inscripcion', $e->getMessage());

            return;
        }

        $this->validate([
            'email' => ['required', 'email', 'max:190'],
        ], [], ['email' => 'correo electrónico']);

        if (! $this->limitar()) {
            return;
        }

        $existe = Jugador::query()->where('email', Str::lower(trim($this->email)))->exists();

        if ($existe) {
            $mandar->handle($this->email, route('torneo.inscripcion', $this->torneo, false));
            $this->enlaceEnviado = true;

            return;
        }

        $reglas = [
            'nombre' => ['required', 'string', 'max:80'],
            'apellidos' => ['required', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'privacidad' => ['accepted'],
        ];
        if ($this->textoDeCondiciones() !== null) {
            $reglas['condiciones'] = ['accepted'];
        }
        $this->validate($reglas, [
            'privacidad.accepted' => 'Hay que aceptar la política de privacidad.',
            'condiciones.accepted' => 'Hay que aceptar las condiciones de inscripción.',
        ], ['nombre' => 'nombre', 'apellidos' => 'apellidos', 'telefono' => 'teléfono']);

        if ($this->sitio_web !== '') {
            $this->enlaceEnviado = true;

            return;
        }

        $jugador = Jugador::query()->create([
            'nombre' => trim($this->nombre),
            'apellidos' => trim($this->apellidos),
            'email' => $this->email,
            'telefono' => trim($this->telefono) !== '' ? trim($this->telefono) : null,
            'consentimiento_en' => now(),
        ]);

        AccesoDelJugador::iniciarSesion(request(), $jugador);
    }

    /** Paso 2. */
    public function inscribirme(InscribirseOnline $inscribirse): void
    {
        $yo = $this->jugador();
        abort_if($yo === null, 403);

        $this->validate([
            'categoria_id' => ['required', 'integer'],
            'companero_email' => ['required', 'email', 'max:190'],
            'companero_nombre' => ['nullable', 'string', 'max:80'],
            'companero_apellidos' => ['nullable', 'string', 'max:120'],
            'companero_telefono' => ['nullable', 'string', 'max:30'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ], [], ['categoria_id' => 'categoría', 'companero_email' => 'correo de tu compañero']);

        if (! $this->limitar()) {
            return;
        }

        $categoria = Categoria::query()->where('torneo_id', $this->torneo->id)->find($this->categoria_id);
        if ($categoria === null) {
            $this->addError('categoria_id', 'Elige una categoría del torneo.');

            return;
        }

        try {
            $inscripcion = $inscribirse->handle($yo, $categoria, [
                'email' => $this->companero_email,
                'nombre' => $this->companero_nombre,
                'apellidos' => $this->companero_apellidos,
                'telefono' => $this->companero_telefono,
            ], trim($this->notas) !== '' ? trim($this->notas) : null);
        } catch (OperacionNoPermitida $e) {
            $this->addError('inscripcion', $e->getMessage());

            return;
        }

        $this->hecha = $inscripcion->id;
    }

    public function render(): View
    {
        $abierta = true;
        $motivo = null;
        try {
            InscribirseOnline::comprobarQueEstaAbierta($this->torneo);
        } catch (OperacionNoPermitida $e) {
            $abierta = false;
            $motivo = $e->getMessage();
        }

        $yo = $this->jugador();

        return view('livewire.publico.inscripcion-en-torneo', [
            'abierta' => $abierta,
            'motivo' => $motivo,
            'yo' => $yo,
            'categorias' => $this->torneo->categorias()->withCount(['inscripciones as confirmadas_count' => fn ($q) => $q->where('estado', EstadoInscripcion::Confirmada->value)])->get(),
            'condiciones' => $this->textoDeCondiciones(),
            'inscripcion' => $this->hecha !== null ? Inscripcion::query()->with(['categoria', 'participante'])->find($this->hecha) : null,
        ]);
    }

    private function jugador(): ?Jugador
    {
        $jugador = Auth::guard('jugador')->user();

        return $jugador instanceof Jugador ? $jugador : null;
    }

    private function textoDeCondiciones(): ?string
    {
        $texto = ValorDeAjuste::obtener(Ajuste::TextoLegalCondiciones);

        return is_string($texto) && trim($texto) !== '' ? $texto : null;
    }

    private function limitar(): bool
    {
        $llave = 'inscripcion-online:'.request()->ip();

        if (RateLimiter::tooManyAttempts($llave, self::ENVIOS_POR_HORA)) {
            $this->addError('inscripcion', 'Demasiados intentos desde aquí. Vuelve a probar dentro de un rato.');

            return false;
        }

        RateLimiter::hit($llave, 3600);

        return true;
    }
}
