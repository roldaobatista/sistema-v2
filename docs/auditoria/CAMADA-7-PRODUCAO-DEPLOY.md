> **NOTA:** Este documento é um índice resumido. Para a auditoria detalhada e completa, consultar:
> - `docs/auditoria/AUDITORIA-GAPS-DOCUMENTACAO-2026-03-25.md` (relatório completo com 156 gaps)
> - `docs/auditoria/RELATORIO-AUDITORIA-GAPS-2026-03-25.md` (sumário executivo)
> - `docs/auditoria/GAP-ANALYSIS-ISO-17025-9001.md` (análise de gaps ISO)

# Camada 7: Produção, Security e Deploy Definitivo

Diretriz de Governabilidade de Produção e Health Checking. Qualquer anomalia lida por um AI-Agent requer análise aprofundada dos manuais desta camada.

## 1. Server de Produção Padrão (Snapshot)

- **IP**: 203.0.113.10
- **Banco**: MySQL 8.0, DB `kalibrium`.
- **Conectividade**: Acesso via chave SSH padrão de `$env:USERPROFILE`.

## 2. Regras de Migration em Produção

Sempre revisar `.cursor/rules/migration-production.mdc` e `.cursor/rules/deploy-production.mdc`:

- **NUNCA** lançar rotinas destrutivas de DB (`migrate:fresh` ou `migrate:reset`) contra o banco de produção!
- Usar verificadores sintáticos dinâmicos (`hasColumn`, `hasTable`) para deltas cumulativos sem conflitos.
- `->after()` para novas tabelas não se aplica em migrations cumulativas retrospectivas.
- Para consertar scripts de legado que falharam no live server, **não** modificar seus arquivos prévios localmente. Sempre adicionar uma `..._create_fix_migration_for_...php`.

## 3. `deploy.sh` e Health Checks

O script `deploy.sh` em live:

- Faz backup diário antes do deploy.
- Instala dependências e limpa cache (`config:clear`, `route:clear`, `view:clear`, `opcache:clear`).
- Executa a migration.

## 4. Alerta de Risco
>
> **[AI_RULE_CRITICAL]**: Os Agentes estão orientados ao MODO PRODUÇÃO ao lidar com este manifesto. Nenhuma operação irrevogável na base de dados será realizada sem expressa Autorização/Confirmação (Prompt de Risco), devendo relatar extensivamente ao Host a intenção com os *rollback vectors* cabíveis.
