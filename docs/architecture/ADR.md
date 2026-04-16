---
type: registry_adr
---
# Architecture Decision Records (ADRs)

> **[AI_RULE]** Ao encontrar um impasse técnico, o agente DEVE consultar esta tabela. Se a ação proposta ferir um ADR "Aceito", a ação deve ser abortada ou recalibrada.

## Registro de Decisões

| ID | Decisão | Status | Data | Contexto |
|---|---|---|---|---|
| 001 | Adoção do padrão Modular Monolith | Aceito | 2024-01 | Microsserviços prematuros criariam complexidade de rede injustificada para o tamanho da equipe |
| 002 | React Vite SPA vs Next.js SSR | SPA Aceito | 2024-01 | ERP é aplicação interna, SEO irrelevante; SPA simplifica deploy e state management |
| 003 | MySQL 8 como banco principal | Aceito | 2024-01 | Compatibilidade com hospedagens brasileiras, JSON columns, window functions |
| 004 | Redis para cache, filas e sessões | Aceito | 2024-01 | Performance superior a database queues; suporte nativo Laravel |
| 005 | Laravel Sanctum (não Passport) | Aceito | 2024-01 | SPA + Mobile PWA não requerem OAuth2 completo; Sanctum mais simples |
| 006 | Spatie Permission para RBAC | Aceito | 2024-01 | Pacote maduro, multi-tenant friendly, amplamente testado |
| 007 | Multi-tenant via coluna `tenant_id` | Aceito | 2024-01 | Database-per-tenant é caro demais para SaaS com muitos tenants pequenos |
| 008 | ULIDs para entidades offline | Aceito | 2024-02 | Técnicos em campo sem internet precisam gerar IDs únicos localmente |
| 009 | Laravel Reverb para WebSockets | Aceito | 2024-03 | Nativo Laravel, sem dependência de Pusher/Soketi; menor custo operacional |
| 010 | Tailwind v4 + React Aria | Aceito | 2024-01 | Acessibilidade WCAG 2.1 AA nativa; design system consistente |
| 011 | Prefixos de tabela por bounded context | Aceito | 2024-01 | `fin_`, `hr_`, `wo_` facilitam identificação visual de ownership |
| 012 | DTOs imutáveis entre módulos | Aceito | 2024-02 | Previne race conditions e acoplamento via Eloquent Models |
| 013 | Portaria 671/2021 compliance nativo | Aceito | 2024-06 | Ponto digital com GPS + selfie obrigatório; hash chain para integridade |
| 014 | PWA ao invés de app nativo | Aceito | 2024-01 | Reduz custo de manutenção; funciona offline via Service Workers |
| 015 | Pest ao invés de PHPUnit direto | Aceito | 2024-01 | Sintaxe mais expressiva; integração nativa Laravel |

## Template para Novas ADRs `[AI_RULE]`

> **[AI_RULE]** Quando o agente ou desenvolvedor tomar uma decisão arquitetural significativa, DEVE registrar aqui seguindo o template:

```markdown
### ADR-XXX: [Título da Decisão]

**Status:** Proposto | Aceito | Depreciado | Substituído por ADR-YYY
**Data:** YYYY-MM-DD
**Contexto:** [Qual problema estávamos enfrentando?]
**Decisão:** [O que decidimos fazer?]
**Consequências:**
- Positivas: [...]
- Negativas: [...]
- Riscos: [...]
```

## Decisões Rejeitadas (Documentação de "Por Que Não")

| Proposta | Status | Motivo da Rejeição |
|----------|--------|-------------------|
| GraphQL ao invés de REST | Rejeitado | Complexidade de segurança multi-tenant; time sem experiência |
| MongoDB para logs | Rejeitado | Mais uma tecnologia para operar; MySQL JSON columns suficientes |
| Kubernetes em produção | Rejeitado | Overkill para deploy single-server atual; Docker Compose suficiente |
| Livewire para formulários | Rejeitado | Inconsistência com SPA React; duplicação de stack frontend |
| Database-per-tenant | Rejeitado | Custo proibitivo para centenas de tenants; migrations complexas |
