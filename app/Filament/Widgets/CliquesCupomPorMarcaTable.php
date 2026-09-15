<?php

namespace App\Filament\Widgets;

use App\Models\EventoCupom;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

/** Etapa 16, prioridade 2: ranking de cliques por marca, últimos 30 dias. */
class CliquesCupomPorMarcaTable extends TableWidget implements HasTable
{
    use InteractsWithTable;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Cliques por marca — últimos 30 dias')
            ->query(
                EventoCupom::query()
                    ->select([
                        'marca_id',
                        DB::raw('marca_id as id'),
                        DB::raw('COUNT(*) as total'),
                    ])
                    ->where('created_at', '>=', now()->subDays(30))
                    ->groupBy('marca_id')
            )
            ->defaultSort('total', 'desc')
            ->columns([
                TextColumn::make('marca.nome')->label('Marca'),
                TextColumn::make('total')->label('Cliques')->numeric()->sortable(),
            ])
            ->paginated([5, 10, 25]);
    }
}
