<?php

namespace App\Filament\Pages;

use App\Enums\Ajuste;
use App\Filament\Avisos;
use App\Filament\MarcaDelOrganizador;
use App\Models\Organizador;
use App\Models\RegistroDeCambio;
use App\Models\User;
use App\Models\ValorDeAjuste;
use App\Tenancy\OrganizadorActual;
use BackedEnum;
use DateTimeZone;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

/**
 * Los ajustes del organizador (solo el propietario): su marca (nombre
 * público, logotipo y colores), el contacto, la privacidad por defecto y
 * los textos legales de su web. Y, para leer, su plan y sus direcciones.
 *
 * @property-read Schema $form
 */
class Ajustes extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Organización';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Ajustes';

    protected static ?string $slug = 'ajustes';

    /** Las claves que lleva esta pantalla. */
    private const AJUSTES = [
        Ajuste::NombrePublico, Ajuste::LogoPath, Ajuste::ColorPrimario, Ajuste::ColorSecundario, Ajuste::ZonaHoraria,
        Ajuste::ContactoEmail, Ajuste::ContactoTelefono, Ajuste::PrivacidadAliasPorDefecto,
        Ajuste::TextoLegalAviso, Ajuste::TextoLegalPrivacidad, Ajuste::TextoLegalCondiciones,
    ];

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $usuario = Filament::auth()->user();

        return $usuario instanceof User && $usuario->esPropietario();
    }

    public function mount(): void
    {
        $this->form->fill(collect(self::AJUSTES)
            ->mapWithKeys(fn (Ajuste $ajuste): array => [$ajuste->value => ValorDeAjuste::obtener($ajuste)])
            ->all());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('web')
                ->label('Ver mi web')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): string => url('/'), shouldOpenInNewTab: true),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $organizador = $this->organizador();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Marca')
                    ->description('Lo que ven los jugadores en la web, en los correos y en el panel.')
                    ->columns(2)
                    ->components([
                        TextInput::make(Ajuste::NombrePublico->value)->label('Nombre público')->required()->maxLength(120)
                            ->helperText('El de la organización tal como quiere que salga.'),
                        FileUpload::make(Ajuste::LogoPath->value)->label('Logotipo')
                            ->image()->disk(MarcaDelOrganizador::DISCO)->directory(fn (): string => MarcaDelOrganizador::carpeta())
                            ->visibility('public')
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'])
                            ->maxSize(4 * 1024)
                            ->helperText('PNG con fondo transparente, cuadrado, de al menos 512 × 512 píxeles. Es también el icono de la aplicación en el móvil.'),
                        ColorPicker::make(Ajuste::ColorPrimario->value)->label('Color principal')->hex()->required()->regex('/^#[0-9a-fA-F]{6}$/')
                            ->helperText('Botones, enlaces y el apartado activo del menú.'),
                        ColorPicker::make(Ajuste::ColorSecundario->value)->label('Color secundario')->hex()->nullable()->regex('/^#[0-9a-fA-F]{6}$/')
                            ->helperText('Detalles de la web pública. En blanco, uno a juego con el principal.'),
                    ]),
                Section::make('Contacto y zona horaria')
                    ->columns(3)
                    ->components([
                        TextInput::make(Ajuste::ContactoEmail->value)->label('Correo de contacto')->email()->maxLength(190)
                            ->helperText('Sale al pie de la web y en los correos a los jugadores.'),
                        TextInput::make(Ajuste::ContactoTelefono->value)->label('Teléfono de contacto')->tel()->maxLength(30),
                        Select::make(Ajuste::ZonaHoraria->value)->label('Zona horaria')->required()->searchable()
                            ->options(fn (): array => array_combine(DateTimeZone::listIdentifiers(DateTimeZone::EUROPE), DateTimeZone::listIdentifiers(DateTimeZone::EUROPE)) + ['Atlantic/Canary' => 'Atlantic/Canary'])
                            ->helperText('La de las horas de los partidos.'),
                    ]),
                Section::make('Privacidad')
                    ->components([
                        Toggle::make(Ajuste::PrivacidadAliasPorDefecto->value)->label('Enseñar el alias de los jugadores en público')
                            ->helperText('Cada torneo lo puede cambiar. Con el alias, en la web no sale el nombre y los apellidos de nadie.'),
                    ]),
                Section::make('Textos legales de su web')
                    ->description('Se enseñan en su dirección (/legal/aviso, /legal/privacidad y en la inscripción). En blanco, la web lo dice en vez de inventarse nada.')
                    ->components([
                        Textarea::make(Ajuste::TextoLegalAviso->value)->label('Aviso legal')->rows(6)->maxLength(20000),
                        Textarea::make(Ajuste::TextoLegalPrivacidad->value)->label('Política de privacidad')->rows(6)->maxLength(20000),
                        Textarea::make(Ajuste::TextoLegalCondiciones->value)->label('Condiciones de inscripción')->rows(6)->maxLength(20000)
                            ->helperText('Lo que acepta cada pareja al inscribirse: normativa, devoluciones, imagen…'),
                    ]),
                Section::make('Plan y direcciones')
                    ->description('Lo que es de la plataforma: se cambia escribiendo a soporte.')
                    ->columns(2)
                    ->components([
                        Text::make(fn (): string => 'Plan «'.$organizador->plan->nombre.'»: '.self::limites($organizador))->columnSpanFull(),
                        Text::make(fn (): string => 'Subdominio: '.$organizador->id.'.'.config()->array('tenancy.central_domains')[0]),
                        Text::make(fn (): string => 'Dominio propio: '.($organizador->dominio ?? 'ninguno')),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('guardar')
                ->footer([
                    Actions::make([
                        Action::make('guardar')->label('Guardar')->submit('guardar'),
                    ]),
                ]),
        ]);
    }

    public function guardar(): void
    {
        abort_unless(self::canAccess(), 403);

        $estado = $this->form->getState();
        $antes = collect(self::AJUSTES)->mapWithKeys(fn (Ajuste $a): array => [$a->value => ValorDeAjuste::obtener($a)])->all();

        /** @var array<string, bool|string|null> $valores */
        $valores = [];
        foreach (self::AJUSTES as $ajuste) {
            $valor = $estado[$ajuste->value] ?? null;
            $valores[$ajuste->value] = match (true) {
                $ajuste === Ajuste::PrivacidadAliasPorDefecto => (bool) $valor,
                $ajuste === Ajuste::ColorPrimario, $ajuste === Ajuste::ColorSecundario => is_string($valor) && $valor !== '' ? strtolower($valor) : null,
                is_string($valor) => trim($valor) === '' ? null : trim($valor),
                default => null,
            };
        }

        $logoAntes = $antes[Ajuste::LogoPath->value];
        if (is_string($logoAntes) && $logoAntes !== '' && $logoAntes !== $valores[Ajuste::LogoPath->value]) {
            Storage::disk(MarcaDelOrganizador::DISCO)->delete($logoAntes);
        }

        ValorDeAjuste::fijar($valores);

        // El sujeto del registro es quien los cambia: los ajustes no tienen id numérico.
        $cambios = array_filter($valores, fn (mixed $valor, string $clave): bool => $valor !== ($antes[$clave] ?? null), ARRAY_FILTER_USE_BOTH);
        $usuario = Filament::auth()->user();
        if ($cambios !== [] && $usuario instanceof User) {
            RegistroDeCambio::anotar('ajustes', $usuario, $usuario, array_intersect_key($antes, $cambios), $cambios);
        }

        Avisos::hecho('Ajustes guardados');
    }

    private static function limites(Organizador $organizador): string
    {
        $plan = $organizador->plan;
        $partes = [
            $plan->torneos_activos === null ? 'torneos activos sin límite' : "{$plan->torneos_activos} torneos activos",
            $plan->parejas_por_torneo === null ? 'parejas sin límite' : "{$plan->parejas_por_torneo} parejas por torneo",
            $plan->categorias_por_torneo === null ? 'categorías sin límite' : "{$plan->categorias_por_torneo} categorías por torneo",
            $plan->exportaciones ? 'con exportaciones' : 'sin exportaciones',
            $plan->dominio_propio ? 'con dominio propio' : 'sin dominio propio',
        ];

        return implode(', ', $partes).'.';
    }

    private function organizador(): Organizador
    {
        return app(OrganizadorActual::class)->organizador();
    }
}
