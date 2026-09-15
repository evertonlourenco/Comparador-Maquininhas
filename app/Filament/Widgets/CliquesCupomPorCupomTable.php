<?php

namespace App\Filament\Widgets;

use App\Models\EventoCupom;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

/**
 * Etapa 16, prioridade 2: ranking de cliques por cupom, últimos 30 dias.
 * Agrupado por `codigo` (a foto do código no momento do clique — ver
 * App\Models\EventoCupom), não por `cupom_id`: um cupom editado ou apagado
 * não pode fazer o clique de ontem desaparecer do ranking de hoje.
 */
class CliquesCupomPorCupomTable extends TableWidget implements HasTable
{
    use InteractsWithTable;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Cliques por cupom — últimos 30 dias')
            ->query(
                EventoCupom::query()
                    ->select([
                        'marca_id',
                        'codigo',
                        DB::raw("CONCAT(marca_id, '_', codigo) as id"),
                        DB::raw('COUNT(*) as total'),
                    ])
                    ->where('created_at', '>=', now()->subDays(30))
                    ->groupBy('marca_id', 'codigo')
            )
            ->defaultSort('total', 'desc')
            ->columns([
                TextColumn::make('marca.nome')->label('Marca'),
                TextColumn::make('codigo')->label('Código'),
                TextColumn::make('total')->label('Cliques')->numeric()->sortable(),
            ])
            ->paginated([5, 10, 25]);
    }
}
