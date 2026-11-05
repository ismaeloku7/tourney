<?php

namespace App\Livewire\Mesa;

use App\Actions\Competicion\AnotarMarcador;
use App\Actions\Competicion\RegistrarResultado;
use App\Actions\Competicion\ValidarResultado;
use App\Avisos\EnlaceDeWhatsApp;
use App\Competicion\Errores\ErrorDeCompeticion;
use App\Competicion\Errores\ResultadoInvalido;
use App\Enums\EstadoPartido;
use App\Filament\Competicion\Marcador;
use App\Livewire\Concerns\MarcadorEnVivo;
use App\Models\Jugador;
use App\Models\Partido;
use App\Models\Pista;
use App\Models\Torneo;
use App\Models\User;
use App\Publico\DatosDelTorneo;
use App\Publico\PermisosDeMesa;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * La mesa en el móvil: elige torneo, día y pista, y ve los partidos en
 * orden con un botón para anotar el resultado (también walkover, retirada
 * o no presentado) o validar el que dejó un jugador. Además empieza cada
 * partido, lleva el marcador en vivo, lo termina con los sets ya puestos
 * y avisa por WhatsApp a la pareja que espera.
 */
#[Layout('components.layouts.publico')]
class MesaDeArbitro extends Component
{
    use MarcadorEnVivo;

    #[Url]
    public ?int $torneo = null;

    #[Url]
    public ?string $dia = null;

    #[Url]
    public ?int $pista = null;

    public ?string $aviso = null;

    public function guardarResultado(RegistrarResultado $registrar): void
    {
        $partido = $this->partidoPermitido((int) $this->editando);

        try {
            $registrar->handle($partido, $this->resultadoDelFormulario(), $this->usuario());
        } catch (ResultadoInvalido $e) {
            $this->addError('resultado', implode(' ', $e->errores));

            return;
        } catch (ErrorDeCompeticion $e) {
            $this->addError('resultado', $e->getMessage());

            return;
        }

        $this->editando = null;
        $this->aviso = 'Resultado guardado.';
    }

    public function validar(int $partido, ValidarResultado $validar): void
    {
        try {
            $validar->handle($this->partidoPermitido($partido), $this->usuario());
        } catch (ErrorDeCompeticion $e) {
            $this->addError('mesa', $e->getMessage());

            return;
        }

        $this->aviso = 'Resultado validado.';
    }

    public function render(): View
    {
        $usuario = $this->usuario();
        $torneos = PermisosDeMesa::torneos($usuario);
        $torneo = $torneos->firstWhere('id', $this->torneo) ?? $torneos->first();

        $partidos = [];
        $pistas = collect();
        $dias = [];

        if ($torneo instanceof Torneo) {
            $this->torneo = $torneo->id;
            foreach ($torneo->fecha_inicio->toPeriod($torneo->fecha_fin) as $d) {
                $dias[$d->toDateString()] = $d->translatedFormat('l j');
            }
            if ($this->dia === null || ! array_key_exists($this->dia, $dias)) {
                $this->dia = array_key_exists(now()->toDateString(), $dias) ? now()->toDateString() : (string) array_key_first($dias);
            }

            $permitidas = PermisosDeMesa::pistas($usuario, $torneo);
            $pistas = Pista::query()->whereIn('id', $torneo->torneoPistas()->pluck('pista_id'))
                ->when($permitidas !== null, fn (Builder $q) => $q->whereIn('id', $permitidas ?? []))
                ->orderBy('orden')->get();

            $datos = new DatosDelTorneo($torneo);
            $partidos = $datos->partidosConHora()
                ->whereDate('franjas.inicio', (string) $this->dia)
                ->whereIn('partidos.pista_id', $this->pista !== null && $pistas->contains('id', $this->pista) ? [$this->pista] : $pistas->pluck('id')->all())
                ->get()
                ->map(fn (Partido $p): array => [
                    'id' => $p->id,
                    'hora' => $p->franja->inicio->format('H:i'),
                    'pista' => $p->pista->nombre ?? '',
                    'categoria' => $p->fase->categoria->nombre,
                    'local' => $p->local->nombre ?? Marcador::lado($p, true),
                    'visitante' => $p->visitante->nombre ?? Marcador::lado($p, false),
                    'resultado' => $p->cerrado() || $p->estado === EstadoPartido::PendienteValidacion ? Marcador::resultado($p) : '',
                    'estado' => $p->estado,
                    'abierto' => in_array($p->estado, [EstadoPartido::Pendiente, EstadoPartido::Programado, EstadoPartido::EnJuego], true),
                    'porEmpezar' => in_array($p->estado, [EstadoPartido::Pendiente, EstadoPartido::Programado], true) && $p->local_id !== null && $p->visitante_id !== null,
                    'enJuego' => $p->estado === EstadoPartido::EnJuego,
                    'marcador' => $p->marcador_en_vivo ?? ['sets' => [], 'juegos' => [0, 0]],
                    'marcadorTexto' => AnotarMarcador::texto($p),
                    'whatsapp' => in_array($p->estado, [EstadoPartido::Pendiente, EstadoPartido::Programado], true) ? $this->enlacesDeWhatsApp($p, $torneo) : [],
                ])->all();
        }

        return view('livewire.mesa.mesa-de-arbitro', [
            'usuario' => $usuario,
            'torneos' => $torneos,
            'torneoActual' => $torneo,
            'dias' => $dias,
            'pistas' => $pistas,
            'partidos' => $partidos,
        ]);
    }

    /**
     * Un enlace de WhatsApp por jugador de la pareja, con el mensaje de
     * «sois los siguientes» y cómo va el partido que ocupa su pista.
     *
     * @return list<array{nombre: string, url: string}>
     */
    private function enlacesDeWhatsApp(Partido $partido, Torneo $torneo): array
    {
        $enPista = Partido::query()->where('pista_id', $partido->pista_id)->where('estado', EstadoPartido::EnJuego->value)->with(['local', 'visitante'])->first();
        $situacion = $enPista === null
            ? 'La pista está libre: podéis entrar.'
            : 'Ahora juegan '.Marcador::lado($enPista, true).' – '.Marcador::lado($enPista, false).(AnotarMarcador::texto($enPista) !== '' ? ' (va '.AnotarMarcador::texto($enPista).')' : '').'.';
        $hora = $partido->franja !== null ? ' Hora prevista: '.$partido->franja->inicio->format('H:i').'.' : '';

        $enlaces = [];
        foreach ([$partido->local, $partido->visitante] as $participante) {
            foreach ($participante === null ? [] : $participante->jugadores as $jugador) {
                $texto = "Hola {$jugador->nombre}, {$torneo->nombre}: sois los siguientes en ".($partido->pista->nombre ?? 'pista').". {$situacion}{$hora} Id calentando.";
                $url = EnlaceDeWhatsApp::para($jugador, $texto);
                if ($url !== null) {
                    $enlaces[] = ['nombre' => $jugador->nombre_completo, 'url' => $url];
                }
            }
        }

        return $enlaces;
    }

    protected function partidoEnVivo(int $id): Partido
    {
        return $this->partidoPermitido($id);
    }

    protected function quienMarca(): User|Jugador
    {
        return $this->usuario();
    }

    private function partidoPermitido(int $id): Partido
    {
        $partido = Partido::query()->with('fase.categoria.torneo')->find($id);
        abort_if($partido === null, 404);
        abort_unless(PermisosDeMesa::puede($this->usuario(), $partido->fase->categoria->torneo, $partido->pista_id), 403);

        return $partido;
    }

    private function usuario(): User
    {
        $usuario = Auth::guard('web')->user();
        abort_unless($usuario instanceof User && $usuario->activo, 403);

        return $usuario;
    }
}
