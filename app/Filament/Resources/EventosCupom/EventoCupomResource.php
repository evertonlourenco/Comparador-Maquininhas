<?php

namespace App\Filament\Resources\EventosCupom;

use App\Filament\Resources\EventosCupom\Pages\ListEventosCupom;
use App\Filament\Resources\EventosCupom\Tables\EventosCupomTable;
use App\Models\EventoCupom;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Etapa 09: só listagem. Não é entidade de domínio a editar pelo painel — é
 * o log de clique em "usar cupom" e de cópia de código, para reconciliar com
 * o relatório de cada parceiro no fim do mês. Sem create/edit/delete de
 * propósito: alterar um evento à mão invalidaria a reconciliação.
 */
class EventoCupomResource extends Resource
{
    protected static ?string $model = EventoCupom::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCursorArrowRipple;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'codigo';

    protected static ?string $slug = 'eventos-cupom';

    protected static ?string $pluralModelLabel = 'Eventos de cupom';

    protected static ?string $modelLabel = 'Evento de cupom';

    public static function table(Table $table): Table
    {
        return EventosCupomTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEventosCupom::route('/'),
        ];
    }
}
