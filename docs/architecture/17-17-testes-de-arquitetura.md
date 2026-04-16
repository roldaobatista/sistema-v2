---
type: root_architecture
---
# Testes de Arquitetura (Pest PHP)

> **[AI_RULE]** Humanos falham e IA "tem alucinações". Se você confiar apendas na documentação, no futuro o código sairá dos eixos. Testes de Arquitetura são as correntes físicas de aço dessa lei.

## 1. Implementação Obrigatória com Pest `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL] A Lei de Teste Anti-Espaguete (`expect()->toBeUsed()`)**
> Nenhuma regra do tipo "Controllers não podem acessar o Banco (Models) diretamente" sobrevive sem validação automatizada de PR (Pull Request).
> A MÁQUINA DEVE CRIAR arquivos PEST PHP em `tests/ArchTest.php` cobrindo isso via asserções sintáticas.

## Exemplo Obrigatório (Copie e Cole)

```php
test('controllers_do_not_use_eloquent_directly')
    ->expect('App\Modules\*\Controllers')
    ->not->toUse('Illuminate\Database\Eloquent\Model')
    ->ignoring('App\Models\User'); // Só permitindo auth middleware

test('services_can_only_be_injected_by_interfaces')
    ->expect('App\Modules\*\Services')
    ->toImplement('App\Contracts\ServiceInterface')
    ->toUseStrictTypes();

test('events_are_dtos')
    ->expect('App\Modules\*\Events')
    ->not->toUse('App\Models')
    ->comment('Eventos so trafegam Scalars ou JSON Strings para evitar bugs temporais na fila');
```

Qualquer feature IA submetida que quebre as asserções rodadas via `./vendor/bin/pest --filter ArchTest` deve ser automaticamente reguada e revertida (`git reset --hard`) na hora de fazer merge/completude.
