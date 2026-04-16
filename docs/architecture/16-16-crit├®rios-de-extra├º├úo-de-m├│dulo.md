---
type: architecture_pattern
id: 16
---
# 16. Critérios para Extração de Novo Módulo

> **[AI_RULE]** Inchar Módulos faz com que o isolamento perca seu sentido. O "Core" não pode aglomerar lógica infinita.

## 1. Quando Desmembrar `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL] A Lei do Foco Único**
> É proibido agrupar lógicas imensas de "Aplicações Independentes" no core. Exemplo: um software nativo de Rastreamento de Caminhões (Telemetria IoT em tempo real). Ele possui um alto rendimento de gravação. Ele DEVE nascer como um módulo `Telemetry` isolado.
> **Gatilho de Extração:**
>
> 1. Possui regras altamente mutáveis diferentes do resto do sistema.
> 2. O Vocabulário de Negócios (Ubiquitous Language) colide. Se "Ticket" significa "Suporte" num canto, e "Ticket" significa "Ingresso" no outro, então devem viver em módulos diferentes.

## 2. Procedimento Operacional Padrão (IA)

Ao autorizar a criação de novo Módulo, o agente IA deve estruturá-lo gerando:

- A Facade Frontal (Service)
- O Service Provider
- A pasta isolada de Routes (`api.php`)
- Os Contratos que ela exporta pros outros módulos.

## 3. Métricas Quantitativas para Extração

Um domínio de negócio deve ser extraído para módulo próprio quando atinge **2 ou mais** dos seguintes critérios:

| Métrica | Limiar de Extração | Exemplo |
|---------|-------------------|---------|
| **Linhas de código** | > 3.000 LOC no service layer | HR/Ponto Digital atingiu 4.500 LOC |
| **Número de Models** | > 5 models exclusivos do domínio | Calibração: Certificate, Equipment, Standard, Measurement, Uncertainty |
| **Frequência de mudança** | > 60% dos commits do mês tocam o domínio | Módulo de OS em fase de maturação |
| **Equipe dedicada** | 2+ desenvolvedores focados exclusivamente | Time de RH separado do time de OS |
| **Requisitos de escala** | Throughput 10x maior que o restante | Telemetria IoT: 1000 writes/s vs 10 writes/s do CRUD |
| **Compliance independente** | Norma regulatória exclusiva | ISO 17025 afeta só calibração, Portaria 671 afeta só RH |
| **Ciclo de deploy** | Precisa de deploys independentes | Módulo financeiro com janela de deploy diferente |

## 4. O que DEVE Permanecer no Monolito

> **[AI_RULE]** Nem tudo deve ser extraído. Módulos pequenos e estáveis ficam melhor dentro do monolito.

### 4.1 Candidatos a Permanecer Integrados

- **Auth/Users**: autenticação, usuários, roles — é transversal a tudo.
- **Tenants**: gestão de tenants, settings — é a espinha dorsal.
- **Notifications**: camada de notificação é utilitária.
- **Common/Shared**: helpers, traits, base classes.
- **Settings**: configurações globais e por tenant.

### 4.2 Candidatos Naturais a Módulo Isolado

- **WorkOrders**: Ordens de Serviço (alta complexidade, muitas entidades).
- **Finance**: Faturamento, contas a pagar/receber, comissões.
- **HR**: Ponto digital, folha, compliance trabalhista.
- **Calibration**: Certificados, padrões, incertezas (ISO 17025).
- **CRM**: Leads, oportunidades, pipeline de vendas.
- **Quotes**: Orçamentos, aprovações, conversão em OS.
- **Inventory**: Estoque, movimentações, rastreamento de peças.

## 5. Processo de Extração — Passo a Passo

### 5.1 Fase 1: Delimitação do Bounded Context

```
1. Identificar TODOS os models que pertencem ao domínio
2. Mapear dependências de entrada (quem chama este domínio?)
3. Mapear dependências de saída (quem este domínio chama?)
4. Definir a Ubiquitous Language — glossário do módulo
5. Documentar os Contratos (interfaces) que o módulo exporta
```

### 5.2 Fase 2: Estrutura de Diretórios

```
app/
├── Modules/
│   └── NomeDoModulo/
│       ├── Controllers/
│       │   └── Api/V1/
│       ├── Services/
│       ├── Models/
│       ├── Requests/
│       ├── Resources/
│       ├── Policies/
│       ├── Events/
│       ├── Listeners/
│       ├── Contracts/          ← Interfaces exportadas
│       ├── Providers/
│       │   └── NomeDoModuloServiceProvider.php
│       └── Routes/
│           └── api.php
```

### 5.3 Fase 3: Service Provider do Módulo

```php
namespace App\Modules\Calibration\Providers;

class CalibrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bindings de contratos
        $this->app->bind(
            \App\Modules\Calibration\Contracts\CertificateServiceInterface::class,
            \App\Modules\Calibration\Services\CertificateService::class,
        );
    }

    public function boot(): void
    {
        // Rotas isoladas do módulo
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        // Migrations isoladas (se necessário)
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }
}
```

## 6. Comunicação entre Módulos `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL]** Módulos NUNCA acessam os Models ou tabelas de outros módulos diretamente. A comunicação ocorre exclusivamente via:

### 6.1 Contratos (Interfaces)

```php
// Módulo Finance exporta este contrato:
namespace App\Modules\Finance\Contracts;

interface InvoiceServiceInterface
{
    public function createFromWorkOrder(int $workOrderId): Invoice;
    public function getBalance(int $customerId): float;
}

// Módulo WorkOrder consome via injeção de dependência:
class WorkOrderService
{
    public function __construct(
        private InvoiceServiceInterface $invoiceService,
    ) {}

    public function complete(WorkOrder $wo): void
    {
        $wo->update(['status' => 'completed']);
        $this->invoiceService->createFromWorkOrder($wo->id);
    }
}
```

### 6.2 Eventos (Desacoplamento Total)

```php
// Módulo WorkOrder dispara evento genérico:
event(new WorkOrderCompleted($workOrder));

// Módulo Finance escuta e reage:
// Em FinanceServiceProvider:
Event::listen(WorkOrderCompleted::class, GenerateInvoiceListener::class);
Event::listen(WorkOrderCompleted::class, CalculateCommissionListener::class);
```

### 6.3 Regras de Comunicação

| Padrão | Quando Usar | Acoplamento |
|--------|------------|-------------|
| **Interface/Contract** | Chamada síncrona com retorno | Médio (compile-time) |
| **Event/Listener** | Side effect sem retorno | Baixo (runtime) |
| **Job via Queue** | Processamento pesado async | Nenhum |
| **Shared DTO** | Transferência de dados tipada | Baixo |

> **[AI_RULE_CRITICAL]** PROIBIDO: `use App\Modules\Finance\Models\Invoice;` dentro do módulo `WorkOrder`. Use o contrato ou evento.

## 7. Quando Extrair para Microsserviço (Futuro)

A extração para microsserviço separado (deploy independente) é o ÚLTIMO recurso, justificável apenas quando:

1. **Escala radicalmente diferente**: O módulo precisa de 10x mais recursos que o restante.
2. **Stack tecnológica diferente**: Ex: módulo de ML que roda em Python.
3. **Requisitos de disponibilidade**: O módulo precisa de 99.99% uptime enquanto o resto tolera 99.9%.
4. **Equipe completamente independente**: Time separado, sprints separados, deploy separado.

> **[AI_RULE]** No estágio atual do Kalibrium, NENHUM módulo justifica microsserviço. O Modular Monolith atende todos os requisitos. Esta seção existe para planejamento futuro.

## 8. Checklist de Extração de Módulo

Ao criar um novo módulo, o agente IA DEVE:

- [ ] Validar que 2+ métricas de extração foram atingidas (Seção 3)
- [ ] Criar a estrutura de diretórios completa (Seção 5.2)
- [ ] Implementar o ServiceProvider com bindings e rotas (Seção 5.3)
- [ ] Registrar o provider em `config/app.php`
- [ ] Definir os Contracts que o módulo exporta
- [ ] Substituir imports diretos por contracts/events
- [ ] Criar testes de integração entre módulos via contracts
- [ ] Documentar o módulo em `docs/modules/`
- [ ] Atualizar o mapa de módulos em `ARQUITETURA.md`
