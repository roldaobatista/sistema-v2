> **NOTA:** Este documento é um índice resumido. Para a auditoria detalhada e completa, consultar:
> - `docs/auditoria/AUDITORIA-GAPS-DOCUMENTACAO-2026-03-25.md` (relatório completo com 156 gaps)
> - `docs/auditoria/RELATORIO-AUDITORIA-GAPS-2026-03-25.md` (sumário executivo)
> - `docs/auditoria/GAP-ANALYSIS-ISO-17025-9001.md` (análise de gaps ISO)

# Camada 4: Módulos Operacionais e E2E Playwright

A governança do nível de Integração (End-to-End) para a arquitetura Kalibrium SaaS. Assegura que o fluxo de negócios não será quebrado pelas engrenagens de backend ou mutações de layout.

## 1. Prioridade e Cobertura de Módulos (E2E)

Os fluxos mais cruciais exigem BDD/E2E:

1. **WorkOrder (17-state machine)**: Desde o draft até fechamento.
2. **Helpdesk/Ticketing**: Validação de SLA policy automática testada (cron simulation).
3. **Ciclo Comercial (CRM Cotações)**: Assinatura e Billing idempotente via Gateway.
4. **Projects (PPM)**: Faturamento via Milestone Billing.
5. **Omnichannel**: Roteamento de Websockets e criação transversal de Deals/Chamados.
6. **Logistics**: Geração de ZPL (AWB) e rastreabilidade logística reversa.
7. **SupplierPortal**: Teste isolado do Magic Link externo e Upload CTe sem sessão local auth.
8. **IoT_Telemetry**: Mock de hardware submetendo payload via TCP/RS-232.
9. **FixedAssets**: Depreciação contábil mensal simulada via time traveling / Cron.
10. **Analytics_BI**: Validação de background ETL Data Export jobs.

## 2. Checklist E2E Obrigatório

A suíte de Playwright localiza os nós de testes baseada majoritariamente no uso de atributos como `data-testid="..."`.

- Todos os botões primários de submissão do CRUD *devem* portar identificadores test-friendly. Exemplo: `<button data-testid="submit-work-order">`.
- Não referenciar classes atreladas a temas para testes de lógica (Exemplo: evitar localizar botão `.bg-blue-500` - pode mudar conforme o CSS customizado por tenant).

## 3. Simulação e Comportamento Determinístico

> **[AI_RULE]**: Componentes Mockados
> Emuladores (Playwright interception) são admitidos na suíte para simular Webhooks e Respostas de Integração de Hardware/NFe sem onerar os serviços ao vivo (Mock Servers).
> Ao construir fluxos novos, testar caminho feliz, infeliz, limite, e falta de rede (Graceful degradation).
