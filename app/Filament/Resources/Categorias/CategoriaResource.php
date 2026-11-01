<?php

namespace App\Filament\Resources\Categorias;

use App\Enums\Rol;
use App\Filament\Resources\Categorias\Pages\EditCategoria;
use App\Filament\Resources\Categorias\Pages\ListCategorias;
use App\Filament\Resources\Categorias\RelationManagers\FasesRelationManager;
use App\Filament\Resources\Categorias\RelationManagers\InscripcionesRelationManager;
use App\Filament\Resources\Categorias\Schemas\CategoriaForm;
use App\Filament\Resources\Categorias\Tables\CategoriasTable;
use App\Models\Categoria;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * La ficha de una categoría: sus datos, sus fases y sus inscripciones. No
 * sale en el menú: se llega desde el torneo (las categorías se crean allí).
 */
class CategoriaResource extends Resource
{
    protected static ?string $model = Categoria::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $slug = 'categorias';

    protected static ?string $modelLabel = 'categoría';

    protected static ?string $pluralModelLabel = 'categorías';

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /** Un administrador de torneo solo llega a las categorías de sus torneos. */
    public static function getEloquentQuery(): Builder
    {
        $consulta = parent::getEloquentQuery();
        $usuario = Filament::auth()->user();

        if ($usuario instanceof User && $usuario->rol === Rol::Administrador) {
            $consulta->whereHas('torneo.administradores', fn (Builder $q) => $q->whereKey($usuario->id));
        }

        return $consulta;
    }

    public static function form(Schema $schema): Schema
    {
        return CategoriaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoriasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            FasesRelationManager::class,
            InscripcionesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategorias::route('/'),
            'edit' => EditCategoria::route('/{record}/configurar'),
        ];
    }
}
