<?php

namespace App\Http\Controllers;

use App\Enums\SegmentoNegocio;
use App\Enums\StatusRevisao;
use App\Enums\TipoOperacao;
use App\Models\Marca;
use App\Models\PrazoRecebimento;
use App\Models\PropostaRecebida;
use App\Support\Antispam\FormularioProtegido;
use App\Support\Dinheiro;
use App\Support\Navegacao;
use App\Support\Uploads\AnexoDeProposta;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Etapa 10: /enviar-proposta. O lojista relata a proposta que recebeu de uma
 * marca que não publica tabela (regra 4) — anônimo, com consentimento
 * explícito de uso agregado. Nunca vira faixa_reportada sozinho: entra como
 * PropostaRecebida::status = pendente e espera revisão humana no painel
 * (regra 10).
 */
class PropostaController extends Controller
{
    /** As 27 UFs — não é dimensão de domínio, não merece tabela própria. */
    private const ESTADOS = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS',
        'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC',
        'SP', 'SE', 'TO',
    ];

    public function create(): View
    {
        return view('propostas.criar', [
            'navegacao' => Navegacao::principal('enviar-proposta'),
            'marcas' => Marca::query()->aceitamRelatos()->ativas()->orderBy('nome')->get(),
            'prazos' => PrazoRecebimento::query()->orderBy('ordem')->get(),
            'segmentos' => SegmentoNegocio::cases(),
            'tiposOperacao' => TipoOperacao::cases(),
            'estados' => self::ESTADOS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (FormularioProtegido::pareceAutomatizado($request)) {
            return redirect()->route('propostas.create')->with('sucesso', 'Proposta enviada. Obrigado por contribuir!');
        }

        // Linhas em branco (a maioria das quatro pré-preenchidas na tela,
        // que o lojista não usou) somem antes da validação — não são erro,
        // são o padrão esperado de quem não vende Pix, por exemplo.
        $linhas = collect($request->input('taxas', []))
            ->filter(fn (array $linha) => filled($linha['percentual'] ?? null))
            ->values()
            ->all();

        $request->merge(['taxas' => $linhas]);

        $validador = Validator::make($request->all(), [
            'marca_id' => ['required', Rule::exists('marcas', 'id')->where('aceita_relatos', true)],
            'prazo_recebimento_id' => ['nullable', 'exists:prazos_recebimento,id'],
            'mensalidade' => ['nullable', 'string', 'max:20'],
            'data_proposta' => ['required', 'date', 'before_or_equal:today'],
            'estado' => ['required', Rule::in(self::ESTADOS)],
            'segmento' => ['required', Rule::enum(SegmentoNegocio::class)],
            'faturamento_aproximado' => ['nullable', 'string', 'max:20'],
            'consentimento_uso_agregado' => ['required', 'accepted'],
            'anexo' => ['nullable', 'file', 'max:8192', AnexoDeProposta::regraDeValidacao()],
            'taxas' => ['required', 'array', 'min:1'],
            'taxas.*.tipo_operacao' => ['required', Rule::enum(TipoOperacao::class)],
            'taxas.*.percentual' => ['required', 'string'],
            'taxas.*.parcelas' => ['nullable', 'integer'],
        ], [
            'taxas.required' => 'Informe ao menos uma taxa da proposta recebida.',
        ]);

        // Regra 11: o percentual chega em pt-BR. Conferir a faixa aqui, e não
        // numa regra de validação solta, porque Dinheiro::doUsuario() é quem
        // sabe ler "2,49" — uma regra de validação padrão leria vírgula como
        // separador de milhar e reprovaria todo número correto.
        $validador->after(function ($v) use ($linhas) {
            foreach ($linhas as $i => $linha) {
                $percentual = Dinheiro::doUsuario($linha['percentual'] ?? null);

                if ($percentual === null || $percentual < 0 || $percentual > 100) {
                    $v->errors()->add("taxas.$i.percentual", 'Percentual inválido.');
                }
            }
        });

        $dados = $validador->validate();

        $taxasRelatadas = collect($linhas)->map(function (array $linha) {
            $tipo = TipoOperacao::from($linha['tipo_operacao']);
            $parcelas = $tipo->permiteParcelamento()
                ? max($tipo->parcelaMinima(), min($tipo->parcelaMaxima(), (int) ($linha['parcelas'] ?? $tipo->parcelaMinima())))
                : 1;

            return [
                'tipo_operacao' => $tipo->value,
                'parcelas' => $parcelas,
                'percentual' => Dinheiro::doUsuario($linha['percentual']),
            ];
        })->values()->all();

        $anexo = $request->hasFile('anexo')
            ? AnexoDeProposta::salvar($request->file('anexo'), 'propostas/anexos')
            : null;

        PropostaRecebida::create([
            'marca_id' => $dados['marca_id'],
            'prazo_recebimento_id' => $dados['prazo_recebimento_id'] ?? null,
            'taxas_relatadas' => $taxasRelatadas,
            'mensalidade' => Dinheiro::doUsuario($dados['mensalidade'] ?? null),
            'data_proposta' => $dados['data_proposta'],
            'estado' => $dados['estado'],
            'segmento' => $dados['segmento'],
            'faturamento_aproximado' => Dinheiro::doUsuario($dados['faturamento_aproximado'] ?? null),
            'anexo_caminho' => $anexo['caminho'] ?? null,
            'anexo_mime' => $anexo['mime'] ?? null,
            'consentimento_uso_agregado' => true,
            'status' => StatusRevisao::Pendente,
        ]);

        return redirect()->route('propostas.create')
            ->with('sucesso', 'Proposta enviada. Obrigado por contribuir com dado real para outros lojistas!');
    }
}
