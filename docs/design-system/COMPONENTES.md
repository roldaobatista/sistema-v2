---
type: design_system
id: components
---
# Diretrizes de Componentes (Design System)

> **[AI_RULE]** O código de UI não é apenas visual, é contratual. Formulários devem possuir controle rígido nativo de erros do backend.

## 1. Regras de Acessibilidade (a11y) `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL] A Lei do Aria-Label Obrigatório**
> É expressamente proibido para qualquer IA Frontend (ex: React, Vue) gerar um componente interativo (`button`, `a`, `dialog`) que não possua um identificador textual claro. Se um botão contiver apenas um ícone (`<IconHome />`), a omissão de um `aria-label="Home"` causará rejeição imediata da feature. O Lighthouse score deve manter >90.

## 2. Tratamento Consistente de Loading e Erros

- Nenhum formulário com Submit assíncrono pode iniciar a request sem travar o estado o botão primário para um visualizador de `loading spinner` / `disabled`. Multiplas requisições destrutivas idênticas causadas por ansiedade do usuário duplo-clicando geram acoplamento corrupto.
- Toda validação do Laravel (`422 Unprocessable Entity`) deve mapear diretamente para o helper de `error` nos fields específicos do UI. O uso puro de `window.alert()` é proibido.

## 3. Convenções de Nomenclatura

| Tipo | Padrão | Exemplo |
|---|---|---|
| Componente de página | `PascalCase` + sufixo `Page` | `QuoteListPage`, `WorkOrderDetailPage` |
| Componente de formulário | `PascalCase` + sufixo `Form` | `ServiceCallForm`, `EmployeeForm` |
| Componente de tabela | `PascalCase` + sufixo `Table` | `InvoiceTable`, `TimeClockTable` |
| Componente de modal | `PascalCase` + sufixo `Modal` / `Dialog` | `ConfirmDeleteDialog`, `QuoteApprovalModal` |
| Hook customizado | `camelCase` com prefixo `use` | `useQuotes`, `useTimeClockEntries` |
| Serviço API | `camelCase` + sufixo `Service` | `quoteService`, `workOrderService` |

## 4. Padrões de Formulário

### 4.1 Inputs de Texto

- Usar componente `Input` com `label`, `name`, `error` (vindo do estado de validação 422).
- Classes Tailwind: `border-brand-200 focus:ring-brand-500 focus:border-brand-500`.
- Inputs obrigatórios exibem asterisco vermelho (`text-status-danger-text`) ao lado do label.

### 4.2 Select / Combobox

- Selects simples (< 10 opções): `<select>` nativo estilizado com Tailwind.
- Selects com busca (> 10 opções): usar componente `Combobox` com busca assíncrona (debounce 300ms).
- Sempre incluir opção placeholder: `"Selecione..."`.

### 4.3 DatePicker

- Formato de exibição: `DD/MM/YYYY` (padrão brasileiro).
- Formato de envio ao backend: `YYYY-MM-DD` (ISO 8601).
- Suporte a range de datas para filtros (data início / data fim).

### 4.4 Upload de Arquivos

- Componente `FileUpload` com drag-and-drop e preview.
- Validação client-side de tipo e tamanho antes do envio.
- Progress bar durante upload.
- Para imagens: preview thumbnail. Para PDFs: ícone + nome do arquivo.

## 5. Padrões de Tabela / DataGrid

- Componente `DataTable` com funcionalidades padrão:
  - Paginação server-side (nunca carregar todos os registros).
  - Ordenação por coluna (clicável no header).
  - Filtros por coluna com debounce de 300ms.
  - Seleção de linhas (checkbox) para ações em lote.
  - Ações por linha: botões de ícone (`edit`, `delete`, `view`) com `aria-label`.
- Estilo de header: `bg-brand-50 text-brand-700 font-semibold text-sm`.
- Estilo de linha hover: `hover:bg-brand-50`.
- Linhas alternadas: `even:bg-gray-50`.
- Célula de status: badge colorido usando tokens `status.*` do design system.

## 6. Padrões de Modal / Dialog

- Usar `<dialog>` nativo ou componente `Modal` com backdrop escuro (`bg-black/50`).
- Shadow do modal: token `shadows.modal`.
- Sempre incluir botão de fechar (X) com `aria-label="Fechar"`.
- Modais de confirmação destrutiva (deletar): botão primário em `status.danger.text` com texto explícito ("Excluir Permanentemente").
- Foco automático no primeiro campo interativo ao abrir.
- Fechar com `Escape` e clique no backdrop.

## 7. Toasts / Notificações

- Posicionamento: canto superior direito, empilhamento vertical.
- Variantes baseadas nos tokens de status:
  - Sucesso: `bg-status-success-bg text-status-success-text` + ícone check.
  - Erro: `bg-status-danger-bg text-status-danger-text` + ícone X.
  - Alerta: `bg-status-warning-bg text-status-warning-text` + ícone exclamação.
  - Info: `bg-status-info-bg text-status-info-text` + ícone info.
- Auto-dismiss após 5 segundos (configurável). Erros não auto-dismiss.
- Botão de fechar manual em cada toast.

## 8. Estados Vazios (Empty States)

- Quando uma listagem não possui dados, exibir componente `EmptyState` com:
  - Ilustração ou ícone contextual (ex: ícone de documento para lista de orçamentos vazia).
  - Texto descritivo: "Nenhum orçamento encontrado".
  - Botão de ação primária: "Criar Primeiro Orçamento" (quando o usuário tem permissão).
- Classes: `text-brand-400` para texto, `text-brand-300` para ícone.

## 9. Error Boundaries

- Toda página deve ser envolvida por um `ErrorBoundary` React.
- Em caso de erro não tratado, exibir tela amigável com:
  - Mensagem: "Algo deu errado. Tente recarregar a página."
  - Botão "Recarregar" que executa `window.location.reload()`.
  - Log do erro no console para debug.
- Erros de rede (timeout, 500): exibir toast de erro + retry automático (máximo 3 tentativas com backoff exponencial).

## 10. Responsividade

- Mobile-first: todos os componentes devem funcionar em telas a partir de 320px.
- Tabelas em mobile: usar layout de cards empilhados ao invés de scroll horizontal.
- Sidebar de navegação: colapsável em telas < 768px (hamburger menu).
- Modais em mobile: ocupam tela inteira (`fullscreen`) ao invés de centralizado flutuante.

---

## 11. Button Variants

### Variantes de Cor

| Variante | Background | Text | Border | Hover | Uso |
|---|---|---|---|---|---|
| `primary` | `bg-brand-900` (#102a43) | `text-white` | nenhuma | `hover:bg-brand-800` (#243b53) | Ação principal: salvar, criar, confirmar |
| `secondary` | `bg-brand-50` (#f0f4f8) | `text-brand-700` (#334e68) | `border border-brand-200` (#bcccdc) | `hover:bg-brand-100` (#d9e2ec) | Ação secundária: cancelar, voltar, filtrar |
| `danger` | `bg-status-danger-text` (#BF2600) | `text-white` | nenhuma | `hover:opacity-90` | Ações destrutivas: excluir, revogar |
| `ghost` | `bg-transparent` | `text-brand-600` (#486581) | nenhuma | `hover:bg-brand-50` (#f0f4f8) | Ações terciárias: links inline, ações de toolbar |

### Tamanhos

| Tamanho | Altura | Padding | Font Size | Uso |
|---|---|---|---|---|
| `sm` | `32px` | `px-3 py-1.5` | `text-sm` (14px) | Ações em linhas de tabela, toolbars compactas |
| `md` | `40px` | `px-4 py-2` | `text-base` (16px) | Padrão para formulários e ações de página |
| `lg` | `48px` | `px-6 py-3` | `text-lg` (18px) | CTAs de destaque, ações de página principal |

### Estado de Loading

- Ao disparar ação assíncrona, o botão DEVE:
  1. Exibir `spinner` animado (SVG 16px, `animate-spin`) substituindo o ícone esquerdo ou prefixando o texto.
  2. Aplicar `disabled` + `opacity-70` + `cursor-not-allowed`.
  3. Manter a largura fixa (`min-w-[original]`) para evitar layout shift.
  4. Texto muda para versão gerúndio quando apropriado: "Salvar" → "Salvando...".
- Classe Tailwind do spinner: `animate-spin h-4 w-4 text-current`.

### Estado Disabled

- `opacity-50 cursor-not-allowed pointer-events-none`.
- Manter variante de cor original (não mudar para cinza).

---

## 12. Input Field Variants

### Estados

| Estado | Border | Background | Label | Uso |
|---|---|---|---|---|
| `default` | `border-brand-200` (#bcccdc) | `bg-white` | `text-brand-700` (#334e68) | Estado inicial, sem interação |
| `focus` | `border-brand-500 ring-2 ring-brand-500/20` (#627d98) | `bg-white` | `text-brand-700` (#334e68) | Input com foco ativo |
| `error` | `border-status-danger-text` (#BF2600) | `bg-status-danger-bg` (#FFEBE6) | `text-status-danger-text` (#BF2600) | Validação falhou (422 do backend) |
| `disabled` | `border-brand-100` (#d9e2ec) | `bg-brand-50` (#f0f4f8) | `text-brand-400` (#829ab1) | Campo não editável neste contexto |
| `readonly` | `border-brand-100` (#d9e2ec) | `bg-brand-50` (#f0f4f8) | `text-brand-700` (#334e68) | Valor visível mas não editável (ex: ID, data de criação) |

### Asterisco de Campo Obrigatório

- Labels de campos `required` DEVEM exibir asterisco vermelho: `<span class="text-status-danger-text ml-0.5">*</span>`.
- O asterisco fica imediatamente após o texto do label, sem quebra de linha.

### Mensagem de Erro

- Posicionada imediatamente abaixo do input, sem gap extra.
- Classes: `text-status-danger-text text-sm mt-1`.
- Ícone de exclamação (`!`) opcional antes do texto.
- Texto vem diretamente da resposta 422 do Laravel, mapeado por field name.
- Apenas um erro exibido por campo (o primeiro da array de erros).

---

## 13. Table Pagination

### Posicionamento

- Barra de paginação posicionada **abaixo da tabela**, separada por `border-t border-brand-100`.
- Padding: `px-4 py-3`.
- Layout: `flex items-center justify-between` — informações à esquerda, controles à direita.

### Componentes da Barra

| Componente | Posição | Formato | Exemplo |
|---|---|---|---|
| Contador de registros | Esquerda | "Mostrando X-Y de Z resultados" | "Mostrando 1-15 de 243 resultados" |
| Seletor de page size | Centro | Select com opções `[10, 15, 25, 50]` | Dropdown: "15 por página" |
| Navegação de páginas | Direita | Botões numéricos + setas prev/next | `< 1 2 3 4 5 >` |

### Regras de Navegação

- Máximo de **5 botões de página** visíveis simultaneamente.
- Quando há mais de 5 páginas, usar ellipsis (`...`) para indicar páginas ocultas: `< 1 ... 4 5 6 ... 20 >`.
- Botão da página atual: `bg-brand-900 text-white font-semibold rounded`.
- Botões de outras páginas: `text-brand-600 hover:bg-brand-50 rounded`.
- Setas prev/next desabilitadas (`opacity-50 cursor-not-allowed`) quando na primeira/última página.
- Page size padrão: `15`.

---

## 14. Breadcrumb

### Estrutura

- Posicionado no topo da página, abaixo do header e acima do título da página.
- Classes container: `flex items-center text-sm text-brand-400 py-2`.

### Separador

- Caractere `/` (slash) entre cada nível.
- Classes do separador: `mx-2 text-brand-300`.

### Item Atual (Último)

- Texto em **bold** (`font-semibold`), cor `text-brand-700`.
- **Sem link** — é o destino atual, não faz sentido ser clicável.

### Itens Anteriores

- Links clicáveis com cor `text-brand-500 hover:text-brand-700 hover:underline`.
- Primeiro item é sempre "Home" ou ícone de casa.

### Comportamento Mobile (< 768px)

- Exibir apenas **seta de voltar** (`←`) + nome da página anterior.
- Classes: `flex items-center text-sm text-brand-500`.
- A seta funciona como link para o nível imediatamente anterior.

### Profundidade Máxima

- Máximo de **4 níveis** visíveis.
- Se a hierarquia exceder 4 níveis, colapsar intermediários com ellipsis: `Home / ... / Categoria / Item Atual`.
- O primeiro (Home) e os 2 últimos níveis são sempre visíveis.

---

## 15. Alert / Notification Positioning

### Toast Notifications

| Propriedade | Valor |
|---|---|
| Posição | Top-right, `top: 16px`, `right: 16px` |
| Z-index | `z-50` (camada suprema, acima de modais) |
| Largura | `min-w-[320px] max-w-[420px]` |
| Border radius | `rounded-lg` (8px) |
| Shadow | `shadow-lg` |
| Animação de entrada | Slide-in da direita, `duration-300 ease-out` |
| Animação de saída | Fade-out, `duration-200 ease-in` |

### Auto-dismiss por Tipo

| Tipo | Tempo | Comportamento |
|---|---|---|
| `success` | 5 segundos | Auto-dismiss com progress bar sutil na base |
| `warning` | 8 segundos | Auto-dismiss com tempo estendido |
| `error` | Sem auto-dismiss | Requer fechamento manual pelo usuário |
| `info` | 5 segundos | Auto-dismiss padrão |

### Empilhamento (Stacking)

- Máximo de **3 toasts** visíveis simultaneamente.
- Novos toasts empurram os anteriores para baixo (`gap-2` entre toasts).
- Se exceder 3, o toast mais antigo é removido automaticamente.
- Cada toast tem botão de fechar (X) com `aria-label="Fechar notificação"`.

### Banner Alerts (Full-width)

- Posicionado no topo da página, abaixo do header principal.
- Largura: `w-full`.
- Padding: `px-4 py-3`.
- Cores seguem os mesmos tokens de status dos toasts (`bg-status-*-bg text-status-*-text`).
- Uso: avisos de sistema, manutenção programada, alertas de conta (ex: "Sua assinatura expira em 3 dias").
- Botão de fechar à direita, persiste no `localStorage` se fechado pelo usuário.

---

## 16. Sidebar Navigation

### Dimensões

| Estado | Largura | Descrição |
|---|---|---|
| Expandida | `256px` (16rem) | Menu completo com ícones + labels de texto |
| Colapsada | `64px` (4rem) | Apenas ícones, com tooltip no hover mostrando o label |

### Animação de Transição

- Duração: `duration-300` (300ms).
- Easing: `ease-in-out`.
- Propriedade animada: `width` + opacidade dos labels de texto.
- Labels de texto fazem fade-out ao colapsar, fade-in ao expandir.
- Classe: `motion-reduce:transition-none` para respeitar `prefers-reduced-motion`.

### Breakpoint de Auto-collapse

- Em telas `>= 1024px` (lg): sidebar visível e controlável pelo usuário (toggle button).
- Em telas `< 1024px`: sidebar inicia colapsada, abre como overlay com backdrop `bg-black/30`.
- Z-index da sidebar: `z-30`.

### Estilo do Item Ativo

| Propriedade | Valor |
|---|---|
| Background | `bg-brand-100` (#d9e2ec) |
| Text | `text-brand-900 font-semibold` (#102a43) |
| Border indicator | `border-l-4 border-brand-900` — barra vertical à esquerda |
| Ícone | `text-brand-900` (mesma cor do texto) |

### Estilo do Item Inativo

| Propriedade | Valor |
|---|---|
| Background | `bg-transparent` |
| Text | `text-brand-500` (#627d98) |
| Hover | `hover:bg-brand-50 hover:text-brand-700` |
| Ícone | `text-brand-400` (#829ab1) |

### Group Headers (Seções)

- Texto uppercase, classes: `text-xs font-semibold tracking-wider text-brand-400 uppercase`.
- Padding: `px-4 pt-6 pb-2`.
- Exemplos: "PRINCIPAL", "FINANCEIRO", "CONFIGURAÇÕES".
- No estado colapsado, group headers ficam ocultos (apenas ícones dos itens são visíveis).
