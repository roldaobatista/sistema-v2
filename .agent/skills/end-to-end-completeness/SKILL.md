---
name: end-to-end-completeness
description: Verificação de completude ponta a ponta. Valida cascata completa antes de reportar tarefa concluída.
trigger: always_on
---

# End-to-End Completeness Verification

## Quando Ativar

Automaticamente antes de reportar qualquer trabalho como concluído.

## Checklist de Verificação

### Backend
- [ ] Migration existe para toda tabela/coluna referenciada?
- [ ] Model existe com $fillable, $casts, relationships corretos?
- [ ] FormRequest existe com regras de validação?
- [ ] Controller implementa todos os métodos necessários?
- [ ] Rota existe em `routes/api.php` com middleware correto?
- [ ] Service/Action isola lógica de negócio (se complexa)?

### Frontend
- [ ] API client function existe em `@/lib/api`?
- [ ] Hook/composable faz chamada API corretamente?
- [ ] Componente trata loading, error, empty states?
- [ ] TypeScript types definidos (zero `any`)?
- [ ] aria-label em elementos interativos?

### Testes
- [ ] Testes de feature/integration para endpoints?
- [ ] Testes unitários para lógica de negócio?
- [ ] Happy path + error path + edge cases cobertos?
- [ ] Testes existentes continuam passando?

### Integridade
- [ ] Frontend compila: `cd frontend && npm run build` → zero erros?
- [ ] Backend testa: `cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage` → zero falhas?
- [ ] Zero console.log, zero dd(), zero TODO/FIXME?

### Segurança e Escopo de Tenant (Harness H1/H2)
- [ ] `tenant_id` é obtido via `$request->user()->current_tenant_id` — nunca do request body?
- [ ] Toda query respeita o global scope `BelongsToTenant` (sem `withoutGlobalScope` indevido)?
- [ ] `tenant_id` e `created_by` são atribuídos no controller, não expostos no FormRequest?
- [ ] Testes cobrem cenário cross-tenant (recurso de outro tenant → 404)?

### Autonomous Harness Multiagente (H0/H4a)
- [ ] Orquestrador não auditou nem corrigiu código em ciclo de camada?
- [ ] A camada tem cinco auditores/subagentes diferentes com contexto limpo?
- [ ] Se houve correção, a camada voltou para nova rodada dos cinco auditores?
- [ ] A 10ª rodada ainda reprovada escalou em vez de aprovar por aproximação?

### Formato de Resposta Harness (H5)
- [ ] A resposta final contém **os 7 itens obrigatórios** na ordem correta?
  - [ ] 1. Resumo do problema (sintoma + causa raiz)
  - [ ] 2. Arquivos alterados (com `path:LN` quando pertinente)
  - [ ] 3. Motivo técnico de cada alteração (POR QUÊ, não O QUÊ)
  - [ ] 4. Testes executados (comando exato copiável)
  - [ ] 5. Resultado dos testes (output real com contagem passed/failed)
  - [ ] 6. Riscos remanescentes
  - [ ] 7. Próximo passo / recomendações
- [ ] Item 8 (Como desfazer) foi incluído quando aplicável? (migrations, mudança de contrato de API, rota pública, deploy/infra, remoção de feature, risco alto)
- [ ] **Nenhuma claim** de "pronto/funcionando/testes passando/validado" sem evidência objetiva de comando executado no mesmo turno (H7)?

## Ação em Caso de Falha

Se qualquer item do checklist falhar:
1. NÃO reportar como concluído (Harness H8 — falha é bloqueante)
2. Implementar/corrigir o item faltante — causa raiz, não mascaramento
3. Re-executar verificação
4. Só reportar quando 100% OK, no formato Harness (7+1 itens)

> **Fonte canônica do formato de resposta:** `.agent/rules/harness-engineering.md` H5.
