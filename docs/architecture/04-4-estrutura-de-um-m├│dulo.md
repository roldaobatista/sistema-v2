---
type: architecture_pattern
id: 04
---
# 04. Estrutura de Diretórios de um Módulo

> **[AI_RULE]** Quando a IA for designada a criar um Módulo Novo que ainda não existe (ex: `SupplyChain`), ela deverá instanciar rigidamente a espinha dorsal abaixo. Nada fora do lugar.

## 1. Topologia de Pastas Isoladas `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL] Estruturação Obrigatória do Domínio**
> Quando criar Módulos (`Bounded Contexts`), não utilize a raiz monolítica padrão (`app/Models`, `app/Http/Controllers`). Use o Padrão de Domínio `app/Modules/<NomeDoModulo>`.
> Se e IA quebrar essa estrutura, o projeto vira Monolito Espaguete.

### Estrutura Imutável

```
app/
 └─ Modules/
    └─ Finance/
       ├─ Actions/         (Lógicas de negócio injetáveis)
       ├─ Controllers/     (Rotas da API, MVC Controller V1, V2)
       ├─ DTOs/            (Value objects tipados cross-borda)
       ├─ Events/          (Classes que informam o sistema)
       ├─ Exceptions/      (Erros focados "InsufficientBalanceException")
       ├─ Jobs/            (Workers redis)
       ├─ Models/          (Eloquent protegido)
       ├─ Providers/       (Bindings locais)
       ├─ Resources/       (Respostas JSON higienizadas)
       └─ Services/        (A ÚNICA interface lida por fora do módulo)
```

## 2. Rastreamento e Auto-Descoberta (Service Providers)

Todo Módulo DEVE criar e registrar um arquivo nomeado `<Modulo>ServiceProvider` atrelado no `bootstrap/providers.php` central do sistema. É nele que suas rotas independentes devem ser registradas isoladamente para não inchar o `routes/api.php` pai.
