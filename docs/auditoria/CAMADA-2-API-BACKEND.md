> **NOTA:** Este documento é um índice resumido. Para a auditoria detalhada e completa, consultar:
> - `docs/auditoria/AUDITORIA-GAPS-DOCUMENTACAO-2026-03-25.md` (relatório completo com 156 gaps)
> - `docs/auditoria/RELATORIO-AUDITORIA-GAPS-2026-03-25.md` (sumário executivo)
> - `docs/auditoria/GAP-ANALYSIS-ISO-17025-9001.md` (análise de gaps ISO)

# Camada 2: API & Backend (Contratos e Rotas)

Esta documentação descreve a governança de API da arquitetura Kalibrium SaaS.

## 1. Regras de Design de Rotas

Todos os endpoints da API (`/api/v1/`) DEVEM seguir convenções RESTful precisas.

- **Controller Naming conventions**:
  - `App\Http\Controllers\Api\V1\Modulo\ControllerNome`
- Todos os verbos HTTP mapeados estritamente (GET, POST, PUT/PATCH, DELETE).
- Sem dependência de rotas dinâmicas perigosas que evadam a checagem de Gate/Policy.

## 2. Contratos e Respostas (API Resources)

Proibido retornar Models nus (`return User::all()`).

- OBRIGATÓRIO: Utilizar classes `JsonResource` (`UserResource`, `EquipmentResource`).
- Paginador estruturado: Utilizar adequadamente coleções com `meta` tags (links de paginação).

## 3. Validação (FormRequests strictly)

> **[AI_RULE]**: Todo POST e PUT DEVE usar FormRequests dedicadas. Código inline `$request->validate()` no Controller ocasiona rejeição automática.

Exemplo OBRIGATÓRIO no método `rules()`:

```php
'status' => ['required', 'string', 'in:active,inactive'],
```

## 4. Comandos de Auditoria da API

Sempre que a malha de rotas for tocada, DEVE-SE garantir a integridade via comando:

```bash
php artisan camada2:validate-routes
```

*(Se este comando listar rotas órfãs ou sem controller, agir proativamente para correção).*
