<?php

namespace App\Filament\Resources\Categorias\RelationManagers;

use App\Competicion\Errores\ConfiguracionInvalida;
use App\Enums\EstadoFase;
use App\Enums\TipoDeFase;
use App\Filament\Avisos;
use App\Filament\Resources\Categorias\Schemas\FaseForm;
use App\Models\Fase;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Las fases de la categoría, en orden: grupos y luego cuadro, una liga,
 * un cuadro directo… Cada una con su configuración validada por el motor.
 * Una fase ya sorteada no se toca desde aquí.
 */
class FasesRelationManager extends RelationManager
{
    protected static string $relationship = 'fases';

    protected static ?string $title = 'Fases';

    protected static ?string $modelLabel = 'fase';

    protected static ?string $pluralModelLabel = 'fases';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components(FaseForm::campos());
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('orden')
            ->reorderable('orden')
            ->recordTitleAttribute('nombre')
            ->emptyStateHeading('Sin fases')
            ->emptyStateDescription('Lo habitual: una fase de grupos y un cuadro con los clasificados. Un torneo pequeño puede ser solo una liga o solo un cuadro.')
            ->columns([
                TextColumn::make('orden')->label('Orden'),
                TextColumn::make('nombre')->label('Fase'),
                TextColumn::make('tipo')->label('Tipo')->badge()->formatStateUsing(fn (TipoDeFase $state): string => $state->etiqueta()),
                TextColumn::make('resumen')->label('Configuración')->state(fn (Fase $record): string => FaseForm::resumen($record)),
                TextColumn::make('formatoPartido.nombre')->label('Formato')->placeholder('El de la categoría'),
                TextColumn::make('estado')->label('Estado')->badge()->formatStateUsing(fn (EstadoFase $state): string => $state->etiqueta()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Añadir fase')
                    ->modalHeading('Nueva fase')
                    ->using(fn (array $data, Action $action): Model => $this->getRelationship()->create(self::comprobar($data, $action))),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (Fase $record): bool => $record->estado === EstadoFase::Pendiente)
                    ->mutateRecordDataUsing(fn (array $data): array => FaseForm::alRellenar($data))
                    ->using(function (Fase $record, array $data, Action $action): Fase {
                        $record->update(self::comprobar($data, $action));

                        return $record;
                    }),
                DeleteAction::make()
                    ->visible(fn (Fase $record): bool => $record->estado === EstadoFase::Pendiente),
            ]);
    }

    /**
     * El DTO del motor valida; si no pasa, el aviso enseña sus frases y el
     * formulario sigue abierto.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function comprobar(array $data, Action $action): array
    {
        try {
            return FaseForm::normalizar($data);
        } catch (ConfiguracionInvalida $e) {
            Avisos::error('La configuración de la fase no es válida', $e->errores);
            $action->halt();
        }

        return $data;
    }
}
