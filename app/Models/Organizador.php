<?php

namespace App\Models;

use App\Enums\EstadoOrganizador;
use App\Http\Middleware\ResolverOrganizadorPorHost;
use Carbon\CarbonInterface;
use Database\Factories\OrganizadorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant;

/**
 * Un organizador inquilino del SaaS: una fila en la base central y una
 * base propia, `organizador_{id}`, con todos sus datos (stancl/tenancy).
 *
 * El id es su slug (`demo`). Es también su subdominio del SaaS
 * (`demo.<dominio-saas>`); además puede tener dominio propio.
 *
 * @property string $id
 * @property string $nombre
 * @property EstadoOrganizador $estado
 * @property int $plan_id
 * @property string $responsable_nombre
 * @property string $responsable_email
 * @property string|null $responsable_password
 * @property string|null $verificacion_token
 * @property CarbonInterface|null $verificacion_expira_en
 * @property CarbonInterface|null $verificado_en
 * @property CarbonInterface|null $suspendido_en
 * @property string|null $suspension_motivo
 * @property CarbonInterface|null $baja_programada_en
 * @property string|null $exportacion_path
 * @property CarbonInterface|null $ultimo_acceso
 * @property-read string|null $dominio
 * @property-read Plan $plan
 */
class Organizador extends Tenant implements TenantWithDatabase
{
    /** @use HasFactory<OrganizadorFactory> */
    use HasDatabase, HasDomains, HasFactory;

    protected $table = 'organizadores';

    /** La contraseña provisional y el token de verificación no salen en logs ni respuestas. */
    protected $hidden = ['responsable_password', 'verificacion_token', 'data'];

    /** Dominio propio indicado al crear, que se guarda en `domains` al crearse. */
    private ?string $dominioPendiente = null;

    /**
     * El id lo pone quien crea el organizador (su slug): ni se genera ni es
     * autoincremental.
     */
    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }

    public function shouldGenerateId(): bool
    {
        return false;
    }

    /**
     * Las columnas reales de la tabla: lo que no esté aquí, stancl lo
     * guardaría en `data`.
     *
     * @return list<string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id', 'nombre', 'estado', 'plan_id', 'responsable_nombre', 'responsable_email', 'responsable_password',
            'verificacion_token', 'verificacion_expira_en', 'verificado_en', 'suspendido_en', 'suspension_motivo',
            'baja_programada_en', 'exportacion_path', 'ultimo_acceso', 'created_at', 'updated_at',
        ];
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoOrganizador::class,
            'verificacion_expira_en' => 'datetime',
            'verificado_en' => 'datetime',
            'suspendido_en' => 'datetime',
            'baja_programada_en' => 'datetime',
            'ultimo_acceso' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Todo organizador tiene su subdominio del SaaS, y su dominio propio si lo trae.
        static::created(function (Organizador $organizador): void {
            $organizador->domains()->create(['domain' => $organizador->id]);

            if ($organizador->dominioPendiente !== null) {
                $organizador->domains()->create(['domain' => $organizador->dominioPendiente]);
                $organizador->dominioPendiente = null;
            }
        });
    }

    /**
     * Forma única de un host: minúsculas, sin puerto, sin punto final y
     * sin `www.`, para que `WWW.Tourney.ClubCeas.es:443` y
     * `tourney.clubceas.es` sean el mismo organizador.
     */
    public static function normalizarDominio(string $host): string
    {
        $host = strtolower(trim($host));
        $host = (string) preg_replace('/:\d+$/', '', $host);
        $host = rtrim($host, '.');

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /** `Organizador::create(['dominio' => 'tourney.clubceas.es'])`: dominio propio. */
    public function setDominioAttribute(string $valor): void
    {
        $dominio = self::normalizarDominio($valor);

        if ($this->exists) {
            $this->domains()->firstOrCreate(['domain' => $dominio]);
        } else {
            $this->dominioPendiente = $dominio;
        }
    }

    /** El dominio propio del organizador, si tiene; el subdominio no cuenta. */
    public function getDominioAttribute(): ?string
    {
        return $this->domains()->where('domain', 'like', '%.%')->value('domain');
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasMany<SuscripcionPlan, $this> */
    public function suscripciones(): HasMany
    {
        return $this->hasMany(SuscripcionPlan::class);
    }

    /** @return HasMany<EventoUso, $this> */
    public function eventosDeUso(): HasMany
    {
        return $this->hasMany(EventoUso::class);
    }

    public function estaActivo(): bool
    {
        return $this->estado === EstadoOrganizador::Activo;
    }

    /**
     * La dirección del organizador para los enlaces de los correos.
     *
     * Si la petición en curso ha entrado por un host de este organizador,
     * ese mismo. Fuera de una petición (cola, consola), su dominio propio
     * si tiene; si no, su subdominio del SaaS, con el esquema y el puerto
     * de APP_URL.
     */
    public function url(string $ruta = ''): string
    {
        // En la cola y en consola no hay petición de verdad: no hay host.
        $porDondeEntro = request()->attributes->get(ResolverOrganizadorPorHost::HOST);

        $actual = tenant();

        if (is_string($porDondeEntro) && $actual instanceof self && $actual->getTenantKey() === $this->getTenantKey()) {
            return rtrim($porDondeEntro, '/').'/'.ltrim($ruta, '/');
        }

        $base = (string) config('app.url');
        $esquema = parse_url($base, PHP_URL_SCHEME) ?: 'https';
        $puerto = parse_url($base, PHP_URL_PORT);
        $host = $this->dominio ?? $this->id.'.'.config()->array('tenancy.central_domains')[0];

        return $esquema.'://'.$host.($puerto ? ':'.$puerto : '').'/'.ltrim($ruta, '/');
    }

    /** Nombre de su base de datos. */
    public function nombreDeBase(): string
    {
        return $this->database()->getName();
    }
}
