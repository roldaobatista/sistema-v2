---
type: root_architecture
title: "Código e Padronização"
---
# Regras de Codebase (Kalibrium SaaS)

> **[AI_RULE]** Formatação e Lints são dezenas de vezes mais rápidos para AIs do que para Humanos.

> **[AI_RULE_CRITICAL] DEPENDÊNCIA DE MÓDULOS DE NEGÓCIO**
> A IA está estritamente **PROIBIDA** de codificar Controllers, Models ou Services sem antes ler o fluxo do seu Bounded Context correspondente em `docs/modules/`. O fluxo em Mermaid define as únicas transições de estado permitidas e os gatilhos arquiteturais (Soft Deletes, Inbound Actions, SLAs).

## 0. Deploy Canônico `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL]** O método canônico de deploy em produção é **Docker Compose** (containers). Referências a Envoyer, symlinks ou deploy direto via PHP no host são INCORRETAS e devem ser ignoradas.

| Item | Valor canônico |
|------|---------------|
| **Método de deploy** | Docker Compose (`docker-compose.prod-https.yml`) |
| **PHP version** | `8.4` (imagem `php:8.4-fpm`) |
| **Backup** | Local `/root/backups/` com rotação de 7 dias |
| **S3 / backup remoto** | Futuro — não implementado ainda |
| **Script de deploy** | `deploy-prod.ps1` (local) → `deploy.sh` (servidor Hetzner) |

Fonte da verdade completa: `docs/operacional/deploy-completo.md`

---

## 1. Padrão Linguístico

- **Códigos PHP/TSX Clássicos:** O core do código (nomes de variáveis, métodos, controllers e lógicas de pastas) **DEVE** permanecer em Inglês (ex: `FinanceService`, `WorkOrder`, `$invoice->calculate()`).
- **Comentários de Fluxo Textual / Doc Blocks:** Para ajudar Devs Híbridos (Man+Machine na América Latina), comentários explicativos grandes e Documentação Externa e APIs OpenAPI devem usar puramente `pt-BR`.

## 2. Padrão Estético de PSR-12 e Pint

A IA DEVE rodar o `laravel/pint` automatizado após edições de código múltiplas. Padrões sem trailing commas nos Arrays, concatenações irregulares e imports não ordenados (Use statements) penalizam a performance de leitura.

## 3. Arquivos Mortos Pós-Deleção

Toda entidade removida via `git rm` do código não é apenas deletada. Caso a lógica possua um equivalente listado num dos `docs/modules/`, a documentação deve ser sincronizada para impedir hallucinações da IA tentando recriá-lo retroativamente por achar o Markdown solto lá.

## 4. Convenções de Nomenclatura `[AI_RULE]`

> **[AI_RULE]** Nomenclatura inconsistente é a principal fonte de confusão para agentes de IA. Seguir estritamente:

### 4.1 Backend (PHP/Laravel)

| Elemento | Convenção | Exemplo |
|----------|-----------|---------|
| Model | Singular, PascalCase | `WorkOrder`, `Invoice`, `TimeClockEntry` |
| Controller | Singular + Controller | `WorkOrderController` |
| Service | Singular + Service | `WorkOrderService` |
| Action | Verbo + Singular + Action | `CreateWorkOrderAction` |
| DTO | Contexto + DTO | `CreateWorkOrderDTO` |
| Event | Singular + PastTense + Event | `WorkOrderCompletedEvent` |
| Listener | Verbo + Contexto | `GenerateInvoiceFromWorkOrder` |
| Job | Verbo + Contexto + Job | `SyncTimeClockJob` |
| Request | Verbo + Singular + Request | `StoreWorkOrderRequest` |
| Resource | Singular + Resource | `WorkOrderResource` |
| Migration | `create_prefixo_tabela_table` | `create_wo_work_orders_table` |
| Tabela | snake_case, plural, com prefixo | `wo_work_orders`, `fin_invoices` |
| Coluna | snake_case | `scheduled_at`, `total_amount` |

### 4.1.1 Prefixos de Migration por Módulo `[AI_RULE]`

> **[AI_RULE]** Toda migration de módulo de negócio DEVE usar o prefixo correspondente. Ex: `create_wo_work_orders_table`, `create_fin_invoices_table`.

| Prefixo | Módulo |
|---------|--------|
| `wo_` | WorkOrders |
| `fin_` | Finance |
| `hr_` | HR |
| `inv_` | Inventory |
| `crm_` | CRM |
| `lab_` | Lab |
| `fis_` | Fiscal |
| `fleet_` | Fleet |
| `iot_` | IoT_Telemetry |
| `proj_` | Projects |
| `proc_` | Procurement |
| `log_` | Logistics |
| `fa_` | FixedAssets |
| `bi_` | Analytics_BI |
| `omni_` | Omnichannel |
| `sup_` | SupplierPortal |
| `help_` | Helpdesk |
| `sched_` | Agenda |
| `qual_` | Quality |
| `inno_` | Innovation |

### 4.2 Frontend (React/TypeScript)

| Elemento | Convenção | Exemplo |
|----------|-----------|---------|
| Componente | PascalCase | `WorkOrderList`, `InvoiceForm` |
| Hook | camelCase com `use` | `useWorkOrders`, `useAuth` |
| Tipo/Interface | PascalCase | `WorkOrder`, `InvoiceFormData` |
| Arquivo componente | PascalCase.tsx | `WorkOrderList.tsx` |
| Arquivo util | camelCase.ts | `formatCurrency.ts` |
| Constante | UPPER_SNAKE_CASE | `API_BASE_URL`, `MAX_UPLOAD_SIZE` |
| API client | camelCase | `workOrderApi.ts` |

## 5. Padrões de Status `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL]** Status de entidades SEMPRE em inglês lowercase. Nunca traduzir para português no banco.

```php
// CORRETO
'status' => 'pending'    // 'paid' | 'partial' | 'cancelled' | 'completed'

// PROIBIDO
'status' => 'Pendente'   // NUNCA em português
'status' => 'PENDING'    // NUNCA em uppercase
```

## 6. Imports e Organização de Código

```php
// Ordem obrigatória de use statements:
use Illuminate\...; // 1. Framework
use App\Contracts\...; // 2. Contracts/Interfaces
use App\Modules\...; // 3. Módulos internos
use App\Http\...; // 4. HTTP layer

// Proibido: imports não utilizados (Pint remove automaticamente)
```

## 7. Checklist de Qualidade por Arquivo `[AI_RULE]`

> **[AI_RULE]** Ao tocar em QUALQUER arquivo, o agente verifica:

- [ ] Imports organizados e sem duplicatas
- [ ] Sem `dd()`, `dump()`, `var_dump()` esquecidos
- [ ] Sem `TODO`, `FIXME`, `HACK` -- implementar agora ou remover
- [ ] Sem código comentado (desativado)
- [ ] Tipagem estrita em parâmetros e retornos (`declare(strict_types=1)`)
- [ ] Model usa `$fillable` (nunca `$guarded = []`)
- [ ] Queries usam eager loading quando acessam relacionamentos
- [ ] FormRequest valida todos os campos recebidos
