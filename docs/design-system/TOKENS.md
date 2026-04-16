# Design Tokens Base (Kalibrium SaaS)

> **[AI_RULE]** O Kalibrium NÃO USA CORE COLORS nativas azuis do browser (hex #0000FF). Utiliza este Design System restrito. A IA DEVE forçar classes Tailwind que orbitam essa paleta, impedindo cores "adivinhadas".

```json
{
  "colors": {
    "brand": {
      "50": "#f0f4f8",
      "100": "#d9e2ec",
      "200": "#bcccdc",
      "300": "#9fb3c8",
      "400": "#829ab1",
      "500": "#627d98",
      "600": "#486581",
      "700": "#334e68",
      "800": "#243b53",
      "900": "#102a43",
      "DEFAULT": "#102a43"
    },
    "status": {
      "success": { "bg": "#E3FCEF", "text": "#006644" },
      "danger": { "bg": "#FFEBE6", "text": "#BF2600" },
      "warning": { "bg": "#FFFAE6", "text": "#FF8B00" },
      "info": { "bg": "#DEEBFF", "text": "#0747A6" }
    }
  },
  "typography": {
    "fontFamily": {
      "sans": ["Inter", "system-ui", "sans-serif"],
      "mono": ["JetBrains Mono", "monospace"]
    }
  },
  "spacing": {
    "container": "1rem",
    "card": "1.5rem"
  },
  "shadows": {
    "card": "0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24)",
    "modal": "0 14px 28px rgba(0,0,0,0.25), 0 10px 10px rgba(0,0,0,0.22)"
  }
}
```

> **[AI_RULE] Bloqueio do Roxo (Ag-Kit Ban)**
> Cores como Purple, Violet, Fuchsia são proibidas para UI primária para evitar templates genéricos ou alucinações de Bootstrap. Utilize as escalas *brand* (Slate/Navy) contidas no arquivo JSON acima e as cores de semântica de alerta (`status.danger`, etc) nativas de softwares de alta densidade (Atlassian style).

---

## Border Radius

| Token | Valor | Uso |
|---|---|---|
| `rounded-none` | `0px` | Elementos sem arredondamento (tabelas densas, badges retangulares) |
| `rounded-sm` | `2px` | Arredondamento sutil (inputs inline, tags) |
| `rounded` | `4px` | Padrão para inputs, selects e botões |
| `rounded-md` | `6px` | Cards, dropdowns, popovers |
| `rounded-lg` | `8px` | Modais, cards destacados |
| `rounded-xl` | `12px` | Cards hero, banners promocionais |
| `rounded-2xl` | `16px` | Containers grandes, seções arredondadas |
| `rounded-full` | `9999px` | Avatares, badges circulares, pills |

---

## Font Size Scale

| Token | Font Size | Line Height | Uso |
|---|---|---|---|
| `text-xs` | `12px` (0.75rem) | `16px` (1rem) | Labels auxiliares, disclaimers, contadores |
| `text-sm` | `14px` (0.875rem) | `20px` (1.25rem) | Texto secundário, captions de tabela, helper text |
| `text-base` | `16px` (1rem) | `24px` (1.5rem) | Corpo de texto padrão, parágrafos, inputs |
| `text-lg` | `18px` (1.125rem) | `28px` (1.75rem) | Subtítulos de seção, labels proeminentes |
| `text-xl` | `20px` (1.25rem) | `28px` (1.75rem) | Títulos de card, headers de tabela |
| `text-2xl` | `24px` (1.5rem) | `32px` (2rem) | Títulos de página secundários |
| `text-3xl` | `30px` (1.875rem) | `36px` (2.25rem) | Títulos de página primários, headers de dashboard |

---

## Line Height

| Token | Valor | Uso |
|---|---|---|
| `leading-none` | `1` | Headings grandes, display text onde o controle é manual |
| `leading-tight` | `1.25` | Headings, títulos de card compactos |
| `leading-snug` | `1.375` | Subtítulos, labels multiline |
| `leading-normal` | `1.5` | Corpo de texto padrão, parágrafos |
| `leading-relaxed` | `1.625` | Textos longos, descrições, help text — melhor legibilidade |

---

## Letter Spacing

| Token | Valor | Uso |
|---|---|---|
| `tracking-tighter` | `-0.05em` | Headings grandes (text-2xl+) para compactação visual |
| `tracking-tight` | `-0.025em` | Títulos de seção, labels de destaque |
| `tracking-normal` | `0em` | Padrão para corpo de texto e inputs |
| `tracking-wide` | `0.025em` | Texto uppercase em botões, badges de status |
| `tracking-wider` | `0.05em` | Labels uppercase pequenos, overline text, group headers de sidebar |

---

## Z-Index Scale

| Token | Valor | Uso |
|---|---|---|
| `z-0` | `0` | Elementos no fluxo normal do documento |
| `z-10` | `10` | Sticky headers de tabela, elementos elevados |
| `z-20` | `20` | Dropdowns, popovers, combobox overlays |
| `z-30` | `30` | Sidebar de navegação (fixa/colapsável) |
| `z-40` | `40` | Modal backdrop (`bg-black/50`) e conteúdo do modal |
| `z-50` | `50` | Toasts, notificações, alerts flutuantes — camada suprema |

> **[AI_RULE] Ordem de Empilhamento Obrigatória**
> A IA DEVE respeitar a hierarquia de z-index acima. Toasts/notificações sempre em `z-50` para sobrepor modais. Modais em `z-40` para sobrepor sidebar. Sidebar em `z-30` para sobrepor dropdowns. Nunca usar valores arbitrários como `z-[999]` ou `z-[9999]` — se um elemento precisa estar acima de tudo, ele é um toast e usa `z-50`.

---

## Animation / Transition

### Durações

| Token | Valor | Uso |
|---|---|---|
| `duration-75` | `75ms` | Micro-interações: hover de botão, mudança de cor |
| `duration-100` | `100ms` | Foco de input, toggle de checkbox |
| `duration-150` | `150ms` | Padrão para maioria das transições de UI |
| `duration-200` | `200ms` | Abertura de dropdown, tooltip appear |
| `duration-300` | `300ms` | Sidebar expand/collapse, slide de painel |
| `duration-500` | `500ms` | Animações de entrada de página, fade de modais |

### Easing Functions

| Token | Valor | Uso |
|---|---|---|
| `ease-linear` | `linear` | Progress bars, animações contínuas |
| `ease-in` | `cubic-bezier(0.4, 0, 1, 1)` | Elementos saindo da tela |
| `ease-out` | `cubic-bezier(0, 0, 0.2, 1)` | Elementos entrando na tela (dropdowns, modais) |
| `ease-in-out` | `cubic-bezier(0.4, 0, 0.2, 1)` | Transições bidirecionais (sidebar, accordion) |

> **[AI_RULE] Respeito a prefers-reduced-motion**
> Toda animação e transição DEVE incluir a media query `@media (prefers-reduced-motion: reduce)` que desabilita ou reduz drasticamente a animação. Em Tailwind, usar a variante `motion-reduce:` (ex: `motion-reduce:transition-none`). Animações decorativas devem ser completamente removidas. Transições essenciais de layout (sidebar collapse) podem manter duração reduzida (`duration-75`) mas nunca ignorar a preferência do usuário.

---

## Form Input Dimensions

| Token | Altura | Padding Horizontal | Font Size | Uso |
|---|---|---|---|---|
| `input-sm` | `32px` (2rem) | `8px` (0.5rem) | `14px` (text-sm) | Filtros inline de tabela, inputs compactos em toolbars |
| `input-md` | `40px` (2.5rem) | `12px` (0.75rem) | `16px` (text-base) | Padrão para formulários — inputs, selects, combobox |
| `input-lg` | `48px` (3rem) | `16px` (1rem) | `18px` (text-lg) | Formulários de destaque, login, campos de busca principal |

> **[AI_RULE] Touch Target Mínimo de 32px**
> Nenhum input, botão ou elemento interativo pode ter altura inferior a `32px` (input-sm). Em contexto mobile, o tamanho mínimo recomendado é `40px` (input-md) seguindo WCAG 2.5.8 Target Size. Inputs menores que 32px causam falha de acessibilidade e serão rejeitados.

---

## Carregamento de Fontes

### Carregamento de Fontes
- **Método:** Self-hosted via `@fontsource` packages
- **Packages:** `@fontsource/inter`, `@fontsource/jetbrains-mono`
- **Import:** No `main.tsx` ou `app.css`:
  ```css
  @import '@fontsource/inter/400.css';
  @import '@fontsource/inter/500.css';
  @import '@fontsource/inter/600.css';
  @import '@fontsource/inter/700.css';
  @import '@fontsource/jetbrains-mono/400.css';
  ```
- **Fallback chain:** `Inter, ui-sans-serif, system-ui, -apple-system, sans-serif`
- **Mono fallback:** `JetBrains Mono, ui-monospace, SFMono-Regular, monospace`

### Tailwind v4 Theme Registration
```css
/* Em app.css com Tailwind v4 */
@theme {
  --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
  --font-mono: 'JetBrains Mono', ui-monospace, monospace;
  --spacing-container: 1.5rem; /* 24px */
  --spacing-card: 1rem; /* 16px */
}
```
