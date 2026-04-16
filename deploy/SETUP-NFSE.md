# Setup NFS-e / NF-e via Focus NF-e

## O que e o Focus NF-e

O [Focus NF-e](https://focusnfe.com.br) e uma API brasileira que simplifica a emissao de documentos fiscais eletronicos (NF-e, NFS-e, CT-e) junto a SEFAZ e prefeituras. O Kalibrium ERP usa o Focus NF-e como provedor fiscal padrao para:

- **NF-e** (Nota Fiscal Eletronica) — venda de produtos/mercadorias
- **NFS-e** (Nota Fiscal de Servico Eletronica) — prestacao de servicos
- Cancelamento, carta de correcao, inutilizacao de numeracao
- Download de DANFE (PDF) e XML autorizados
- Webhooks para atualizacao assincrona de status

## Como obter credenciais

1. Acesse [focusnfe.com.br](https://focusnfe.com.br) e crie uma conta
2. No painel administrativo, va em **API Tokens**
3. Copie o token de API (sera usado como `FOCUSNFE_TOKEN`)
4. A conta ja vem com acesso ao ambiente de **homologacao** (testes) gratuitamente
5. Para emitir notas reais, ative o ambiente de **producao** (requer plano pago e certificado digital A1)

## Variaveis de ambiente obrigatorias

Adicione ao `.env` do backend:

```env
# Provedor fiscal ativo
FISCAL_PROVIDER=focusnfe

# Token de autenticacao do Focus NF-e
FOCUSNFE_TOKEN=seu_token_aqui

# Ambiente: homologation (testes) | production (notas reais)
FOCUSNFE_ENV=homologation

# Secret para validar webhooks do Focus NF-e (gere um valor aleatorio seguro)
FISCAL_WEBHOOK_SECRET=gere-um-secret-seguro-aqui

# Padrao: natureza da operacao e regime tributario
FISCAL_NATUREZA_OPERACAO="Prestacao de Servicos"
FISCAL_REGIME_TRIBUTARIO=1
```

### Valores de `FISCAL_REGIME_TRIBUTARIO`

| Valor | Regime |
|-------|--------|
| 1 | Simples Nacional |
| 2 | Simples Nacional — Excesso de sublimite |
| 3 | Regime Normal (Lucro Presumido/Real) |

## Testar em modo homologacao

1. Defina `FOCUSNFE_ENV=homologation` no `.env`
2. Nenhum certificado digital e necessario em homologacao
3. As notas emitidas sao fictcias — nao tem validade fiscal
4. Use os endpoints da API normalmente:

```bash
# Emitir NFS-e de teste
curl -X POST https://seu-dominio.com/api/v1/fiscal/nfse \
  -H "Authorization: Bearer SEU_JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "prestador": { "cnpj": "00000000000000" },
    "tomador": { "cnpj": "11111111111111", "razao_social": "Cliente Teste" },
    "servico": {
      "valor_servicos": 100.00,
      "discriminacao": "Servico de teste em homologacao",
      "item_lista_servico": "01.01"
    }
  }'
```

5. Verifique o status via `GET /api/v1/fiscal/status/{protocolo}`

## Para producao

1. Mude `FOCUSNFE_ENV=production`
2. No painel do Focus NF-e, faca upload do **certificado digital A1** (.pfx) da empresa
3. Configure os dados fiscais do emitente (CNPJ, IE, endereco, etc.) no painel do Focus NF-e
4. Teste emitindo uma nota de valor baixo antes de operar em escala

## Endpoints disponveis no Kalibrium

### Emissao
| Metodo | Endpoint | Descricao |
|--------|----------|-----------|
| POST | `/api/v1/fiscal/nfe` | Emitir NF-e |
| POST | `/api/v1/fiscal/nfse` | Emitir NFS-e |
| POST | `/api/v1/fiscal/nfe/from-work-order/{id}` | NF-e a partir de OS |
| POST | `/api/v1/fiscal/nfse/from-work-order/{id}` | NFS-e a partir de OS |
| POST | `/api/v1/fiscal/nfe/from-quote/{id}` | NF-e a partir de orcamento |

### Consulta e download
| Metodo | Endpoint | Descricao |
|--------|----------|-----------|
| GET | `/api/v1/fiscal/notas` | Listar notas fiscais |
| GET | `/api/v1/fiscal/notas/{id}` | Detalhes de uma nota |
| GET | `/api/v1/fiscal/status/{protocolo}` | Consultar status na SEFAZ |
| GET | `/api/v1/fiscal/notas/{id}/pdf` | Download DANFE (PDF) |
| GET | `/api/v1/fiscal/notas/{id}/xml` | Download XML autorizado |

### Acoes
| Metodo | Endpoint | Descricao |
|--------|----------|-----------|
| POST | `/api/v1/fiscal/notas/{id}/cancelar` | Cancelar nota |
| POST | `/api/v1/fiscal/notas/{id}/carta-correcao` | Carta de correcao |
| POST | `/api/v1/fiscal/inutilizar` | Inutilizar numeracao |

### Webhook (callback do Focus NF-e)
| Metodo | Endpoint | Descricao |
|--------|----------|-----------|
| POST | `/api/v1/fiscal/webhook` | Recebe callbacks do Focus NF-e |

> O webhook e protegido pelo middleware `verify.fiscal_webhook` que valida o `FISCAL_WEBHOOK_SECRET`.

## Configuracao do webhook no Focus NF-e

No painel do Focus NF-e, configure a URL de callback:

```
https://seu-dominio.com/api/v1/fiscal/webhook
```

O Kalibrium recebe os callbacks automaticamente e atualiza o status das notas no banco de dados.

## Arquitetura do codigo

```
backend/app/Services/Fiscal/
  FiscalProvider.php              # Interface (Strategy pattern)
  FocusNFeProvider.php            # Adapter Focus NF-e
  NuvemFiscalProvider.php         # Adapter Nuvem Fiscal (alternativa)
  ResilientFiscalProvider.php     # Decorator com Circuit Breaker + fallback
  FiscalResult.php                # Value object de resultado
  Contracts/
    FiscalGatewayInterface.php    # Interface para gateway externo

backend/config/
  fiscal.php                      # Configuracao dedicada do modulo fiscal
  services.php                    # Tambem contem config do focusnfe (legacy)
```

O sistema usa o **Strategy Pattern** com **Circuit Breaker**: se o Focus NF-e ficar indisponivel, o circuito abre e pode fazer fallback para o Nuvem Fiscal automaticamente via `ResilientFiscalProvider`.
