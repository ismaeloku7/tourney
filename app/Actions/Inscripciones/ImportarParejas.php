<?php

namespace App\Actions\Inscripciones;

use App\Competicion\Errores\OperacionNoPermitida;
use App\Enums\EstadoInscripcion;
use App\Enums\EstadoParticipante;
use App\Enums\Genero;
use App\Enums\Modalidad;
use App\Enums\OrigenInscripcion;
use App\Importaciones\InformeDeImportacion;
use App\Importaciones\LectorDeHoja;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Jugador;
use App\Models\Participante;
use App\Models\RegistroDeCambio;
use App\Models\Torneo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Importa parejas y jugadores de un Excel o CSV a un torneo: una fila por
 * pareja, con su categoría (o nivel), el nombre del equipo y los dos
 * jugadores con su correo y su teléfono.
 *
 * - La categoría se busca por nombre en el torneo; si no está, se crea.
 * - Un jugador se reconoce por su correo si el nombre coincide. Un correo
 *   que ya es de otra persona (familias que comparten uno) no se le pone:
 *   el jugador se crea sin correo y se avisa.
 * - Una pareja «sin compañero» entra pendiente, con un solo jugador, para
 *   completarla después desde el panel.
 * - Una pareja que ya está en la categoría (mismo nombre) no se repite:
 *   importar dos veces el mismo archivo no duplica nada.
 */
final class ImportarParejas
{
    /** Cabecera normalizada => campo. La primera que aparezca gana. */
    private const COLUMNAS = [
        'categoria' => ['categoria', 'nivel'],
        'equipo' => ['nombre del equipo', 'equipo', 'pareja', 'nombre de la pareja'],
        'codigo' => ['codigo', 'code'],
        'nombre1' => ['participante 1', 'jugador 1', 'nombre jugador 1'],
        'nombre2' => ['participante 2', 'jugador 2', 'nombre jugador 2'],
        'email1' => ['email participante 1', 'email jugador 1', 'correo participante 1', 'correo jugador 1', 'email 1', 'correo 1'],
        'email2' => ['email participante 2', 'email jugador 2', 'correo participante 2', 'correo jugador 2', 'email 2', 'correo 2'],
        'telefono1' => ['telefono participante 1', 'telefono jugador 1', 'telefono 1', 'movil 1'],
        'telefono2' => ['telefono participante 2', 'telefono jugador 2', 'telefono 2', 'movil 2'],
        'nivel1' => ['nivel numerico p1', 'nivel jugador 1', 'nivel p1'],
        'nivel2' => ['nivel numerico p2', 'nivel jugador 2', 'nivel p2'],
    ];

    public function __construct(private readonly InscribirPareja $inscribir) {}

    public function handle(Torneo $torneo, string $ruta, string $extension, ?User $usuario = null, bool $avisar = false, bool $confirmar = true): InformeDeImportacion
    {
        $informe = new InformeDeImportacion;

        try {
            $filas = LectorDeHoja::leer($ruta, $extension, function (array $cabeceras): bool {
                $columnas = self::columnas($cabeceras);

                return isset($columnas['categoria'], $columnas['nombre1']);
            });
        } catch (RuntimeException $e) {
            $informe->errores[] = $e->getMessage().' Hacen falta al menos «Categoría» (o «Nivel») y «Jugador 1» (o «Participante 1»).';

            return $informe;
        } catch (Throwable) {
            $informe->errores[] = 'No se ha podido leer el archivo: tiene que ser un Excel (.xlsx) o un CSV.';

            return $informe;
        }

        if ($filas === []) {
            $informe->errores[] = 'El archivo no tiene filas.';

            return $informe;
        }

        $columnas = self::columnas(array_keys($filas[0]));

        foreach ($filas as $i => $fila) {
            $numero = $i + 2; // la 1 es la cabecera
            $dato = fn (string $campo): string => isset($columnas[$campo]) ? trim($fila[$columnas[$campo]] ?? '') : '';

            try {
                DB::transaction(fn () => $this->importarFila($torneo, $numero, $dato, $informe, $usuario, $avisar, $confirmar));
            } catch (OperacionNoPermitida $e) {
                $informe->errores[] = "Fila {$numero}: ".$e->getMessage();
            }
        }

        RegistroDeCambio::anotar('importar_parejas', $torneo, $usuario, null, [
            'parejas' => $informe->parejas,
            'sin_companero' => $informe->sinCompanero,
            'jugadores_nuevos' => $informe->jugadoresNuevos,
            'errores' => count($informe->errores),
        ]);

        return $informe;
    }

    /** @param  callable(string): string  $dato */
    private function importarFila(Torneo $torneo, int $numero, callable $dato, InformeDeImportacion $informe, ?User $usuario, bool $avisar, bool $confirmar): void
    {
        $nombreCategoria = $dato('categoria');
        $nombre1 = $dato('nombre1');

        if ($nombreCategoria === '' || $nombre1 === '' || self::faltaCompanero($nombre1)) {
            throw new OperacionNoPermitida('sin categoría o sin el primer jugador: no se importa.');
        }

        $categoria = $this->categoria($torneo, $nombreCategoria, $informe);
        $equipo = $dato('equipo');

        if ($equipo !== '' && Participante::query()->where('categoria_id', $categoria->id)->where('nombre', $equipo)->exists()) {
            $informe->yaEstaban++;

            return;
        }

        $codigo = $dato('codigo');
        $notas = $codigo !== '' ? "Importada · código {$codigo}" : 'Importada';

        $primero = $this->jugador($nombre1, $dato('email1'), $dato('telefono1'), $dato('nivel1'), $numero, $informe);

        $nombre2 = $dato('nombre2');
        if ($nombre2 === '' || self::faltaCompanero($nombre2)) {
            $this->parejaSinCompanero($categoria, $primero, $equipo, $notas.' · falta compañero', $usuario);
            $informe->sinCompanero++;

            return;
        }

        $segundo = $this->jugador($nombre2, $dato('email2'), $dato('telefono2'), $dato('nivel2'), $numero, $informe);
        $inscripcion = $this->inscribir->handle($categoria, $primero, $segundo, OrigenInscripcion::Importacion, $usuario, $confirmar, $notas, $avisar);

        if ($equipo !== '') {
            $inscripcion->participante->update(['nombre' => Str::limit($equipo, 160, '')]);
        }

        $informe->parejas++;
    }

    private function categoria(Torneo $torneo, string $nombre, InformeDeImportacion $informe): Categoria
    {
        $existente = Categoria::query()->where('torneo_id', $torneo->id)->get()
            ->first(fn (Categoria $c): bool => LectorDeHoja::normalizar($c->nombre) === LectorDeHoja::normalizar($nombre));

        if ($existente !== null) {
            return $existente;
        }

        $informe->categoriasNuevas[] = $nombre;

        return Categoria::query()->create([
            'torneo_id' => $torneo->id,
            'nombre' => Str::limit($nombre, 80, ''),
            'modalidad' => Modalidad::Parejas,
            'genero' => Genero::Abierto,
            'nivel' => Str::limit($nombre, 40, ''),
            'minimo_participantes' => 0,
            'orden' => (int) Categoria::query()->where('torneo_id', $torneo->id)->max('orden') + 1,
        ]);
    }

    private function jugador(string $nombreCompleto, string $email, string $telefono, string $nivel, int $numero, InformeDeImportacion $informe): Jugador
    {
        [$nombre, $apellidos] = self::partirNombre($nombreCompleto);
        $email = Str::lower($email);
        $emailValido = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

        if ($email !== '' && ! $emailValido) {
            $informe->avisos[] = "Fila {$numero}: el correo «{$email}» de {$nombre} {$apellidos} no es válido; se crea sin correo.";
        }

        $mismaPersona = fn (Jugador $j): bool => LectorDeHoja::normalizar($j->nombre.' '.$j->apellidos) === LectorDeHoja::normalizar($nombre.' '.$apellidos);

        if ($emailValido) {
            $conEseCorreo = Jugador::query()->where('email', $email)->first();

            if ($conEseCorreo !== null && $mismaPersona($conEseCorreo)) {
                $informe->jugadoresExistentes++;

                return $conEseCorreo;
            }

            if ($conEseCorreo !== null) {
                $informe->avisos[] = "Fila {$numero}: {$nombre} {$apellidos} comparte el correo {$email} con {$conEseCorreo->nombre_completo}; se crea sin correo (no podrá entrar en su portal hasta que le pongas uno propio).";
                $emailValido = false;
            }
        }

        if (! $emailValido) {
            $sinCorreo = Jugador::query()->whereNull('email')->whereNull('anonimizado_en')->get()->first($mismaPersona);
            if ($sinCorreo !== null) {
                $informe->jugadoresExistentes++;

                return $sinCorreo;
            }
        }

        $original = $telefono;
        $telefono = preg_replace('/[\s.\-()]/', '', $telefono) ?? '';
        if ($telefono !== '' && preg_match('/^\+?\d{6,15}$/', $telefono) !== 1) {
            $informe->avisos[] = "Fila {$numero}: el teléfono «{$original}» de {$nombre} {$apellidos} no es un teléfono; se deja vacío.";
            $telefono = '';
        }

        $informe->jugadoresNuevos++;

        return Jugador::query()->create([
            'nombre' => Str::limit($nombre, 80, ''),
            'apellidos' => Str::limit($apellidos, 120, ''),
            'email' => $emailValido ? $email : null,
            'telefono' => $telefono !== '' ? $telefono : null,
            'nivel_declarado' => $nivel !== '' ? Str::limit($nivel, 40, '') : null,
        ]);
    }

    /** Una pareja a la que le falta el compañero: un solo jugador, pendiente, sin ocupar plaza. */
    private function parejaSinCompanero(Categoria $categoria, Jugador $jugador, string $equipo, string $notas, ?User $usuario): void
    {
        $yaInscrito = Participante::query()->where('categoria_id', $categoria->id)
            ->whereHas('inscripcion', fn ($q) => $q->where('estado', '!=', EstadoInscripcion::Baja->value))
            ->whereHas('participanteJugadores', fn ($q) => $q->where('jugador_id', $jugador->id))
            ->exists();

        if ($yaInscrito) {
            throw new OperacionNoPermitida("{$jugador->nombre_completo} ya está inscrito en esta categoría.");
        }

        $participante = Participante::query()->create([
            'categoria_id' => $categoria->id,
            'nombre' => $equipo !== '' ? Str::limit($equipo, 160, '') : Participante::nombreDesdeJugadores($jugador),
            'estado' => EstadoParticipante::Activo,
        ]);
        $participante->participanteJugadores()->create(['jugador_id' => $jugador->id, 'orden' => 1, 'confirmado_en' => now()]);

        $inscripcion = Inscripcion::query()->create([
            'categoria_id' => $categoria->id,
            'participante_id' => $participante->id,
            'estado' => EstadoInscripcion::Pendiente,
            'origen' => OrigenInscripcion::Importacion,
            'notas' => $notas,
            'registrada_por' => $usuario?->id,
        ]);

        RegistroDeCambio::anotar('inscribir', $inscripcion, $usuario, null, ['participante' => $participante->nombre, 'estado' => EstadoInscripcion::Pendiente->value]);
    }

    /**
     * «Adrianna Hernandez Ros» → [«Adrianna», «Hernandez Ros»]. Lo que viene
     * todo en mayúsculas o todo en minúsculas se pasa a nombre propio.
     *
     * @return array{0: string, 1: string}
     */
    public static function partirNombre(string $completo): array
    {
        $completo = trim((string) preg_replace('/\s+/u', ' ', $completo));

        if ($completo === mb_strtoupper($completo) || $completo === mb_strtolower($completo)) {
            $completo = mb_convert_case(mb_strtolower($completo), MB_CASE_TITLE);
        }

        $partes = explode(' ', $completo, 2);

        return [$partes[0], $partes[1] ?? ''];
    }

    private static function faltaCompanero(string $texto): bool
    {
        return str_contains(LectorDeHoja::normalizar($texto), 'falta');
    }

    /**
     * @param  list<string>  $cabeceras
     * @return array<string, string> campo => cabecera del archivo
     */
    private static function columnas(array $cabeceras): array
    {
        $columnas = [];
        foreach (self::COLUMNAS as $campo => $alias) {
            foreach ($alias as $nombre) {
                if (in_array($nombre, $cabeceras, true)) {
                    $columnas[$campo] = $nombre;

                    break;
                }
            }
        }

        return $columnas;
    }
}
