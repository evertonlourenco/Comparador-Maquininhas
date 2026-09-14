# Máquina Certa — Manual de marca

Versão 1.0 · Setembro 2026
Submarca do grupo Monetizando.

---

## 01 — Posicionamento

> A escolha é do usuário. O papel da marca é mostrar os números completos — taxas, aluguel, custo de adesão e prazo de recebimento — e deixar a decisão clara.

| | |
|---|---|
| **Para quem** | Autônomos, MEIs e pequenos empreendedores escolhendo a primeira ou a próxima maquininha. |
| **Tom de voz** | Direto e prático. Frases curtas, número antes do adjetivo, zero jargão de adquirência sem explicação. |
| **Transparência** | Parcerias e descontos são sempre sinalizados. Nenhuma posição de ranking é vendida. |
| **Relação com a mãe** | Submarca endossada: o navy do Monetizando é a base e a assinatura "by Monetizando" acompanha o logo. |

---

## 02 — Tipografia

**Saira** — títulos, números, botões. Google Fonts. Pesos 400 / 600 / 700.
Mesma família da marca mãe. Condensada o suficiente para títulos longos e para números grandes em tabelas de taxas.

**Figtree** — textos, rótulos, tabelas. Google Fonts. Pesos 400 / 500 / 600.
Substitui a Gotham do manual do Monetizando. Geométrica, licença aberta, numerais tabulares para alinhar colunas de percentual.

### Escala

| Estilo | Especificação |
|---|---|
| H1 | Saira 700 · 40–56px · letter-spacing -2,5% · line-height 1,05 |
| H2 | Saira 600 · 28–32px · letter-spacing -1,5% · line-height 1,15 |
| Corpo | Figtree 400 · 16px · line-height 1,6 |
| Número | Saira 700 · tabular · letter-spacing -2% |
| Rótulo | Figtree 600 · 11px · letter-spacing +14% · caixa alta |

Import:

```html
<link href="https://fonts.googleapis.com/css2?family=Saira:wght@400;600;700&family=Figtree:wght@400;500;600&display=swap" rel="stylesheet">
```

---

## 03 — Cores

### Principais

| Nome | Hex | RGB | Uso |
|---|---|---|---|
| Navy Monetizando | `#18264B` | 24 38 75 | Cor dominante. Fundos de cabeçalho e rodapé, títulos, ícones, texto de destaque. |
| Verde Certo | `#009C82` | 0 156 130 | Cor de ação e aprovação. Botão principal, melhor taxa, selos de economia. |
| Cinza | `#C8C8C8` | 200 200 200 | Bordas, divisores e estados desativados. Nunca em texto sobre branco. |
| Preto | `#140F10` | 20 15 16 | Texto corrido longo e material impresso em uma cor. |

### Apoio de interface

| Nome | Hex |
|---|---|
| Fundo | `#F6F7F9` |
| Borda | `#E4E7EC` |
| Texto secundário | `#5A6478` |
| Verde claro | `#E8F5F1` |
| Verde escuro (texto) | `#00705E` |
| Alerta | `#C0392B` |

### Cores reservadas na família

Laranja `#D26432` pertence ao Monetizando Negócios e não deve aparecer no Máquina Certa. Roxo `#694FA4`, azul `#0059AB` e o próprio `#009C82` constam no manual do grupo como cores de submarca; o verde foi realocado aqui por estar fora de uso.

### Contraste

- Branco sobre navy — 14,1:1 (AAA)
- Navy sobre branco — 14,9:1 (AAA)
- Branco sobre verde — 3,2:1 — só em corpo 18px+ ou peso 600 a partir de 14px
- Para texto pequeno em verde, use `#00705E`

---

## 04 — Logo

O símbolo é uma maquininha vista de frente com um check ocupando o visor: o objeto e o veredito no mesmo desenho. O visor é sempre vazado na cor do fundo; o check é sempre o verde, exceto nas versões monocromáticas.

### Versões

| Versão | Uso |
|---|---|
| Horizontal com assinatura | Uso principal, cabeçalho do site |
| Horizontal | Espaços reduzidos, e-mail, barra fixa |
| Vertical com assinatura | Redes sociais, materiais quadrados |
| Símbolo isolado | Favicon, app, avatar |
| Negativa | Sobre navy, preto ou foto escura |
| Monocromática | Impressão em uma cor, carimbo |

### Área de proteção e tamanho mínimo

A margem livre em torno do logo equivale à largura do visor do símbolo em todos os lados. Nenhum elemento gráfico ou texto entra nessa área.

- Logo horizontal: mínimo 140px de largura em digital
- Símbolo isolado: mínimo 24px

### Usos incorretos

- Não distorcer as proporções
- Não usar a versão colorida sobre o verde
- Não alterar as cores do símbolo
- Não aplicar sombra, contorno ou relevo

---

## 05 — Interface

### Botões

| Tipo | Estilo |
|---|---|
| Primário | Fundo `#009C82`, texto branco, Saira 600 15px, padding 14px 26px |
| Secundário | Fundo `#18264B`, texto branco |
| Terciário | Contorno 1,5px `#18264B`, texto navy |
| Desativado | Fundo `#E4E7EC`, texto `#9AA0AB` |

Raio 6px, altura mínima 48px em mobile. Verde só para a ação principal da tela — um por vez.

### Selos

| Selo | Estilo |
|---|---|
| MENOR TAXA | Fundo `#E8F5F1`, texto `#00705E` |
| DESCONTO PARCEIRO | Fundo `#18264B`, texto branco |
| SEM ALUGUEL | Fundo `#F6F7F9`, borda `#E4E7EC`, texto `#5A6478` |

Figtree 600 · 12px · letter-spacing +6% · caixa alta · raio 4px.
"Desconto parceiro" é obrigatório sempre que houver comissão envolvida na oferta.

### Card de comparação

Raio 10px. Borda 1px `#E4E7EC` no estado padrão; borda 1,5px `#009C82` com faixa superior verde e rótulo "MELHOR PARA SEU PERFIL" no card destacado. Grade de três números (débito, crédito, 12x) em Saira 700 22px com rótulo Figtree 10,5px caixa alta.

---

## 06 — Arquivos

Todos os PNGs têm fundo transparente (exceto os favicons, que têm o quadrado navy) e ficam em `assets/`. Os arquivos com sufixo `-2x` são o dobro da resolução, para telas retina.

| Arquivo | Uso |
|---|---|
| `logo-horizontal-assinatura.png` | Cabeçalho do site |
| `logo-horizontal.png` | Barra fixa, e-mail |
| `logo-horizontal-assinatura-negativo.png` | Rodapé navy |
| `logo-horizontal-negativo.png` | Fundo escuro sem assinatura |
| `logo-horizontal-mono-navy.png` · `-mono-branco.png` | Impressão em uma cor |
| `logo-vertical.png` · `-assinatura` · `-negativo` | Social, materiais quadrados |
| `simbolo-512/192/64/32.png` | Avatar, app, ícone |
| `favicon-512/192/64/32.png` | Favicon e PWA (fundo navy) |

### Tokens para o código

```css
--mc-navy: #18264B;
--mc-green: #009C82;
--mc-green-dark: #00705E;
--mc-green-soft: #E8F5F1;
--mc-gray: #C8C8C8;
--mc-black: #140F10;
--mc-bg: #F6F7F9;
--mc-border: #E4E7EC;
--mc-text-2: #5A6478;
--mc-alert: #C0392B;
--mc-font-display: 'Saira', sans-serif;
--mc-font-text: 'Figtree', sans-serif;
--mc-radius: 6px;
```
