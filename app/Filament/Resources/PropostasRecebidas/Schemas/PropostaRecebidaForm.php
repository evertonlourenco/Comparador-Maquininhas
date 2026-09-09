<?php

namespace App\Filament\Resources\PropostasRecebidas\Schemas;

use App\Enums\StatusRevisao;
use App\Enums\TipoOperacao;
use App\Models\PropostaRecebida;
use App\Support\Dinheiro;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Só a seção "Revisão" é editável — o resto é o que o lojista relatou, fixo
 * (regra 10: aqui não se corrige o relato, só se decide o que fazer com ele).
 */
class PropostaRecebidaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('O que foi relatado')
                ->columns(2)
                ->components([
                    TextEntry::make('marca.nome')->label('Marca'),
                    TextEntry::make('prazoRecebimento.nome_exibicao')->label('Prazo de recebimento')->placeholder('Não informado'),
                    TextEntry::make('data_proposta')->label('Data da proposta')->date('d/m/Y'),
                    TextEntry::make('estado')->label('Estado'),
                    TextEntry::make('segmento')->label('Segmento'),
                    TextEntry::make('mensalidade')->label('Mensalidade')
                        ->state(fn (PropostaRecebida $record): string => $record->mensalidade !== null ? Dinheiro::real((float) $record->mensalidade) : 'Não informado'),
                    TextEntry::make('faturamento_aproximado')->label('Faturamento aproximado')
                        ->state(fn (PropostaRecebida $record): string => $record->faturamento_aproximado !== null ? Dinheiro::real((float) $record->faturamento_aproximado) : 'Não informado'),
                    TextEntry::make('taxas_relatadas')->label('Taxas')->columnSpanFull()
                        ->state(fn (PropostaRecebida $record): string => collect($record->taxas_relatadas)
                            ->map(fn (array $t) => sprintf(
                                '%s%s: %s',
                                TipoOperacao::from($t['tipo_operacao'])->getLabel(),
                                $t['parcelas'] > 1 ? " ({$t['parcelas']}x)" : '',
                                Dinheiro::percentual((float) $t['percentual'])
                            ))->implode(' · ')),
                ]),

            Section::make('Anexo')
                ->visible(fn (PropostaRecebida $record): bool => filled($record->anexo_caminho))
                ->components([
                    TextEntry::make('anexo_mime')->label('Tipo do arquivo enviado')->placeholder('—'),
                ]),

            Section::make('Revisão')
                ->columns(2)
                ->components([
                    Select::make('status')
                        ->options(StatusRevisao::class)
                        ->required(),
                    Textarea::make('observacao_admin')
                        ->label('Observação interna')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
