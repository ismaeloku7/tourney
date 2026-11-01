<?php

namespace App\Livewire;

use App\Actions\Plataforma\SolicitarAltaDeOrganizador;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * «Crear mi organización» en el dominio de la plataforma: nombre,
 * subdominio, responsable y contraseña. Con campo trampa y cinco altas por
 * IP y hora. Al enviar, la organización queda pendiente hasta que confirme
 * el correo.
 */
#[Layout('components.layouts.publico')]
#[Title('Crear mi organización')]
class AltaDeOrganizador extends Component
{
    private const ALTAS_POR_HORA = 5;

    public string $nombre = '';

    public string $slug = '';

    public string $responsable_nombre = '';

    public string $email = '';

    public string $clave = '';

    public string $clave_confirmation = '';

    public bool $privacidad = false;

    /** Campo trampa: invisible para una persona; un robot lo rellena. */
    public string $sitio_web = '';

    public bool $enviada = false;

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:160'],
            'slug' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/'],
            'responsable_nombre' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:190'],
            'clave' => ['required', 'confirmed', Password::default()],
            'privacidad' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'slug.regex' => 'La dirección solo admite minúsculas, números y guiones, sin empezar ni acabar en guión.',
            'privacidad.accepted' => 'Hay que aceptar la política de privacidad.',
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['nombre' => 'nombre', 'slug' => 'dirección', 'responsable_nombre' => 'nombre del responsable', 'email' => 'correo electrónico', 'clave' => 'contraseña'];
    }

    /** La dirección que se propone a partir del nombre. */
    public function propuesta(): string
    {
        return Str::of($this->nombre)->slug()->limit(30, '')->trim('-')->value();
    }

    public function updatedNombre(): void
    {
        if ($this->slug === '') {
            $this->slug = $this->propuesta();
        }
    }

    public function enviar(SolicitarAltaDeOrganizador $solicitar): void
    {
        $this->slug = Str::lower(trim($this->slug)) ?: $this->propuesta();
        $this->validate();

        if ($this->sitio_web !== '') {
            // Un robot: se le da las gracias y no se crea nada.
            $this->enviada = true;

            return;
        }

        $llave = 'alta-organizador:'.request()->ip();

        if (RateLimiter::tooManyAttempts($llave, self::ALTAS_POR_HORA)) {
            throw ValidationException::withMessages(['nombre' => 'Se han creado demasiadas organizaciones desde aquí. Vuelva a intentarlo dentro de una hora.']);
        }

        RateLimiter::hit($llave, 3600);

        $solicitar->handle($this->slug, $this->nombre, $this->responsable_nombre, $this->email, $this->clave);

        $this->reset('clave', 'clave_confirmation');
        $this->enviada = true;
    }

    public function render(): View
    {
        return view('livewire.alta-de-organizador', [
            'dominio' => config()->array('tenancy.central_domains')[0],
        ]);
    }
}
