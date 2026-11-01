<?php

namespace App\Filament\Resources\Torneos\RelationManagers;

use App\Actions\Torneos\DuplicarCategoria;
use App\Enums\EstadoInscripcion;
use App\Enums\Genero;
use App\Filament\Avisos;
use App\Filament\Resources\Categorias\CategoriaResource;
use App\Filament\Resources\Categorias\Schemas\CategoriaForm;
use App\Models\Categoria;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Las categorías del torneo (Masculina 3ª, Mixta B…): cada una con su
 * cupo, su formato y su orden, que es también la prioridad del
 * planificador. Las fases y las inscripciones se llevan desde su ficha
 * («Configurar»). «Duplicar» copia una con sus fases para no rehacerla.
 */
class CategoriasRelationManager extends RelationManager
{
    protected static string $relationship = 'categorias';

    protected static ?string $title = 'Categorías';

    protected static ?string $modelLabel = 'categoría';

    protected static ?string $pluralModelLabel = 'categorías';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(CategoriaForm::campos());
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->recordTitleAttribute('nombre')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount([
                'fases',
                'inscripciones as confirmadas_count' => fn (Builder $q) => $q->where('estado', EstadoInscripcion::Confirmada->value),
                'inscripciones as pendientes_count' => fn (Builder $q) => $q->where('estado', EstadoInscripcion::Pendiente->value),
            ]))
            ->columns([
                TextColumn::make('orden')->label('Orden'),
                TextColumn::make('nombre')->label('Categoría'),
                TextColumn::make('genero')->label('Género')->badge()->formatStateUsing(fn (Genero $state): string => $state->etiqueta()),
                TextColumn::make('nivel')->label('Nivel')->placeholder('—'),
                TextColumn::make('confirmadas_count')->label('Parejas')
                    ->state(fn (Categoria $record): string => $record->getAttribute('confirmadas_count').($record->cupo !== null ? ' / '.$record->cupo : '')
                        .($record->getAttribute('pendientes_count') > 0 ? ' · '.$record->getAttribute('pendientes_count').' pendientes' : ''))
                    ->tooltip('Confirmadas (y pendientes). Se ven y se gestionan en «Configurar» › Inscripciones.'),
                TextColumn::make('fases_count')->label('Fases'),
                TextColumn::make('formatoPartido.nombre')->label('Formato')->placeholder('Predeterminado'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('configurar')
                    ->label('Configurar')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->url(fn (Categoria $record): string => CategoriaResource::getUrl('edit', ['record' => $record])),
                Action::make('duplicar')
                    ->label('Duplicar')
                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->color('gray')
                    ->visible(fn (): bool => Gate::allows('create', Categoria::class))
                    ->modalHeading(fn (Categoria $record): string => 'Duplicar «'.$record->nombre.'»')
                    ->modalDescription('Se crea una categoría nueva en este torneo con los mismos datos (cupo, precio, formato…) y, si quieres, las mismas fases. No se copian parejas, inscripciones, grupos ni partidos.')
                    ->modalSubmitActionLabel('Duplicar')
                    ->fillForm(fn (Categoria $record): array => [
                        'nombre' => $record->nombre.' (copia)',
                        'genero' => $record->genero->value,
                        'nivel' => $record->nivel,
                        'con_fases' => true,
                    ])
                    ->schema([
                        TextInput::make('nombre')->label('Nombre de la nueva')->required()->maxLength(80),
                        Select::make('genero')->label('Género')->options(Genero::opciones())->required(),
                        TextInput::make('nivel')->label('Nivel')->maxLength(40),
                        Toggle::make('con_fases')->label('Copiar también las fases')->helperText('Con su configuración de grupos, cuadro y formato. Nacen sin sortear.'),
                    ])
                    ->action(function (Categoria $record, array $data): void {
                        $usuario = Filament::auth()->user();
                        $nueva = app(DuplicarCategoria::class)->handle($record, [
                            'nombre' => trim((string) $data['nombre']),
                            'genero' => Genero::from((string) $data['genero']),
                            'nivel' => filled($data['nivel'] ?? null) ? trim((string) $data['nivel']) : null,
                        ], (bool) ($data['con_fases'] ?? true), $usuario instanceof User ? $usuario : null);

                        Avisos::hecho('Categoría duplicada', '«'.$nueva->nombre.'», con '.$nueva->fases()->count().' fases.');
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
