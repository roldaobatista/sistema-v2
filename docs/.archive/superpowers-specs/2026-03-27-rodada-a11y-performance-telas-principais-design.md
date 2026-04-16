# Rodada A11y e Performance — Telas Principais

## Objetivo

Executar uma rodada curta e de alto impacto para reduzir problemas reais de acessibilidade e melhorar a performance percebida nas telas mais usadas do sistema, sem abrir uma refatoração ampla do frontend inteiro.

## Escopo

Esta rodada cobre apenas quatro áreas:

1. Autenticação
   - `LoginPage`
   - `ForgotPasswordPage`
   - `ResetPasswordPage`
2. Home operacional
   - `DashboardPage`
   - `ErrorBoundary`
3. CRM principal
   - `CrmDashboardPage`
   - `CrmPipelinePage`
4. Analytics
   - `AnalyticsHubPage`
   - componentes mais visíveis e pesados relacionados ao hub e aos charts usados nessa área

## Problemas-alvo

### Acessibilidade

- inputs sem `label` visível ou `aria-label`
- elementos com `onClick` sem suporte por teclado
- headings principais ausentes ou inconsistentes
- pontos óbvios de navegação/uso sem feedback semântico suficiente

### Performance

- custo inicial alto em rotas principais
- imports pesados carregados cedo demais
- componentes de analytics/charts com impacto visual e de bundle acima do ideal

## Abordagens avaliadas

### 1. Rodada focada nas telas principais

Corrigir primeiro as telas com maior uso e maior retorno prático, reduzindo risco e mantendo o diff controlado.

**Vantagens**
- entrega rápida
- menor chance de regressão
- melhora percebida logo nas áreas mais importantes

**Desvantagens**
- não elimina o passivo global do frontend

**Recomendação:** esta abordagem.

### 2. Varredura ampla em todo o frontend

Aplicar correções em muitas páginas de uma vez.

**Vantagens**
- cobre mais arquivos de uma só vez

**Desvantagens**
- diff grande
- mais risco de ruído e regressão
- pior rastreabilidade

## Design da solução

### Frente 1: Acessibilidade real

- adicionar `label` ou `aria-label` onde faltar
- trocar elementos clicáveis inadequados por botões/controles semânticos quando necessário
- adicionar suporte de teclado onde houver interação manual
- garantir presença e hierarquia mínima de headings nas páginas do escopo

### Frente 2: Performance fina

- manter e expandir lazy loading quando houver ganho real nas rotas do escopo
- revisar imports pesados nos pontos principais de CRM e Analytics
- evitar mudanças cosméticas sem impacto mensurável

## Limites desta rodada

- não tentar zerar todos os warnings heurísticos do `ux_audit.py`
- não fazer refatoração estrutural ampla do design system
- não mexer em módulos fora do escopo principal desta rodada

## Verificação

### Obrigatório

- `python .agent/skills/frontend-design/scripts/accessibility_checker.py frontend/src`
- `cd frontend && npm run build`
- testes focados das páginas alteradas

### Evidência esperada

- redução de issues reais nas telas-alvo
- build verde
- diff pequeno e objetivo

## Riscos

- alguns alertas dos scripts são heurísticos e podem continuar existindo fora do escopo
- parte do ganho de bundle pode ser parcial, porque há chunks grandes compartilhados

## Resultado esperado

Ao final desta rodada:

- autenticação, dashboard, CRM principal e analytics ficam mais acessíveis
- rotas principais ficam mais leves onde houver oportunidade concreta
- sobra documentada apenas a dívida fora do escopo desta rodada
