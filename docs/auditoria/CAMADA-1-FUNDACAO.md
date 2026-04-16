> **NOTA:** Este documento é um índice resumido. Para a auditoria detalhada e completa, consultar:
> - `docs/auditoria/AUDITORIA-GAPS-DOCUMENTACAO-2026-03-25.md` (relatório completo com 156 gaps)
> - `docs/auditoria/RELATORIO-AUDITORIA-GAPS-2026-03-25.md` (sumário executivo)
> - `docs/auditoria/GAP-ANALYSIS-ISO-17025-9001.md` (análise de gaps ISO)

# Camada 1: Fundação, Autenticação e Permissões

Esta documentação compõe a governança nível 1 da arquitetura Kalibrium SaaS e serve como checklist obrigatório de auditoria (AIDD).

## 1. Padrões de Fundação e Isolamento

O sistema adota **Row-Level Multi-Tenancy**.
Todo e qualquer acesso a banco deve respeitar o Tenant Isolation.

- **Checklist de Isolamento:**
  - [ ] A tabela correspondente possui `tenant_id` (`foreignId('tenant_id')->constrained()`).
  - [ ] O Controller injeta ativamente `Auth::user()->current_tenant_id` na criação.
  - [ ] O Frontend repassa adequadamente o tenant contexto (gerenciado por cookie/estado global).

## 2. Autenticação e Autorização (Spatie Permission)

A plataforma utiliza o Spatie Laravel Permission para o RBAC.

- **Roles Padrão Obrigatórias:**
  - `admin`: Controle global e painel.
  - `manager`: Nível gerencial focado.
  - `technician`: Trabalhador de campo (acesso a WorkOrders e Checklists).

## 3. Comandos de Validação da Camada

A validação de privilégios e permissões não atribuídas se dá por via automatizada:

```bash
php artisan camada1:audit-permissions
```

*(Nota de ambiente: a implementação do comando faz parte da completude final obrigatória).*

> **[AI_RULE]**: Ao alterar permissões em backend, SEMPRE rodar o auditor em fase final de commit. O agente não deve finalizar a tarefa manual de implementação de ACL sem esta garantia estrutural.
