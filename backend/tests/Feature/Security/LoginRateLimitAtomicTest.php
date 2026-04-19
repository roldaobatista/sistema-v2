<?php

namespace Tests\Feature\Security;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regressão sec-04 — Rate limit de login não pode depender de get+put
 * (TOCTOU). Deve usar contador atômico (Cache::add + Cache::increment ou
 * equivalente) para que requisições concorrentes não consigam ultrapassar
 * o limite de 5 tentativas dentro da janela de 15 minutos.
 *
 * OWASP ASVS V2.2.1 — Anti-automation controls must prevent credential
 * stuffing/brute force attacks.
 */
class LoginRateLimitAtomicTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private string $email;

    private string $throttleKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->email = 'ratelimit-victim@example.com';
        $this->user = User::factory()->create([
            'email' => $this->email,
            'password' => Hash::make('CorrectHorse!Battery9Staple'),
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        // Chave usada pelo AuthController. Limpar para garantir isolamento.
        $this->throttleKey = 'login_attempts:127.0.0.1:'.strtolower($this->email);
        Cache::forget($this->throttleKey);
        Cache::forget($this->throttleKey.':ttl');
    }

    protected function tearDown(): void
    {
        Cache::forget($this->throttleKey);
        Cache::forget($this->throttleKey.':ttl');
        parent::tearDown();
    }

    /**
     * Cenário A — 5 tentativas falhas sequenciais → 6ª retorna 429.
     */
    public function test_sixth_failed_attempt_returns_429_after_five_failures(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/api/v1/login', [
                'email' => $this->email,
                'password' => 'wrong-password-'.$i,
            ]);

            $this->assertNotEquals(429, $response->getStatusCode(), "Tentativa {$i} não deveria estar bloqueada (limite é 5).");
            $response->assertStatus(422);
        }

        // 6ª tentativa — deve estar bloqueada.
        $response = $this->postJson('/api/v1/login', [
            'email' => $this->email,
            'password' => 'wrong-password-6',
        ]);

        $response->assertStatus(429);
        $response->assertJsonFragment(['message' => 'Conta bloqueada. Muitas tentativas de login. Tente novamente em 15 minutos.']);
    }

    /**
     * Cenário B — Simulação de corrida com leituras "stale":
     *
     * Se 5 requisições concorrentes lerem o contador ao mesmo tempo com
     * valor 0 antes que qualquer uma grave, o padrão buggy (get + put)
     * faria cada uma gravar 1, resultando em 5 falhas registradas apenas
     * como 1 no cache — o atacante poderia fazer muitas mais que 5
     * antes do bloqueio.
     *
     * Como o teste roda single-thread, injetamos o bug cirurgicamente:
     * setamos manualmente o contador para 4 ANTES da 5ª e da 6ª
     * requisições. Se o controller for atômico (Cache::increment), a 5ª
     * falha vira 5 no cache e a 6ª recebe 429. Se o controller for
     * get+put (lê 4, grava 5) a 5ª também resulta em 5 — mesmo comportamento.
     *
     * O discriminador real: usamos um Cache spy para gravar TODAS as
     * operações que o controller fez e asseverar que NÃO há um par
     * get+put na mesma chave de counter (padrão inseguro). Em vez disso
     * o controller deve chamar increment ou equivalente.
     */
    public function test_controller_does_not_use_non_atomic_get_put_on_counter_key(): void
    {
        $counterKey = $this->throttleKey;

        // Envolvemos o store do cache para registrar operações.
        $operations = [];
        $realStore = Cache::getStore();

        // Usamos Mockery para espionar o facade. Delegamos ao store real.
        $spy = \Mockery::mock($realStore)->makePartial();
        $spy->shouldReceive('get')->andReturnUsing(function ($key) use ($realStore, &$operations, $counterKey) {
            if ($key === $counterKey) {
                $operations[] = ['op' => 'get', 'key' => $key];
            }

            return $realStore->get($key);
        });
        $spy->shouldReceive('put')->andReturnUsing(function ($key, $value, $seconds = null) use ($realStore, &$operations, $counterKey) {
            if ($key === $counterKey) {
                $operations[] = ['op' => 'put', 'key' => $key, 'value' => $value];
            }

            return $realStore->put($key, $value, $seconds ?? 900);
        });
        $spy->shouldReceive('increment')->andReturnUsing(function ($key, $value = 1) use ($realStore, &$operations, $counterKey) {
            if ($key === $counterKey) {
                $operations[] = ['op' => 'increment', 'key' => $key];
            }

            return $realStore->increment($key, $value);
        });
        $spy->shouldReceive('add')->andReturnUsing(function ($key, $value, $seconds = null) use ($realStore, &$operations, $counterKey) {
            if ($key === $counterKey) {
                $operations[] = ['op' => 'add', 'key' => $key];
            }
            $store = $realStore;
            if (method_exists($store, 'add')) {
                return $store->add($key, $value, $seconds ?? 900);
            }

            // Fallback: emular add (só grava se ausente).
            if ($store->get($key) === null) {
                return $store->put($key, $value, $seconds ?? 900);
            }

            return false;
        });

        Cache::swap(new \Illuminate\Cache\Repository($spy));

        // 1 requisição falha.
        $this->postJson('/api/v1/login', [
            'email' => $this->email,
            'password' => 'wrong-concurrent',
        ])->assertStatus(422);

        // Proibido: put com valor > 0 derivado de leitura prévia (padrão
        // TOCTOU get+put). Um `put(counter, 0)` é aceitável como seed
        // inicial (fallback de Cache::add em drivers sem add nativo);
        // o que NÃO pode acontecer é `put(counter, N+1)` com N vindo de get.
        $unsafePut = array_values(array_filter(
            $operations,
            fn ($o) => $o['op'] === 'put' && (int) ($o['value'] ?? 0) > 0
        ));

        $this->assertEmpty(
            $unsafePut,
            'Controller usou padrão não-atômico get+put(valor>0) no contador de login. '.
            'Deve usar Cache::increment (que é atômico no driver) para evitar TOCTOU. '.
            'Operações registradas: '.json_encode($operations)
        );

        // Deve haver ao menos 1 increment (operação atômica real) OU um
        // mecanismo equivalente (ex: RateLimiter::hit que usa increment
        // internamente). Aceitamos as duas variantes explicitamente.
        $hasIncrement = count(array_filter($operations, fn ($o) => $o['op'] === 'increment')) > 0;
        $this->assertTrue(
            $hasIncrement,
            'Controller deve chamar Cache::increment no contador para garantir atomicidade. '.
            'Operações registradas: '.json_encode($operations)
        );
    }

    /**
     * Cenário B.2 — Simulação de race explícita com leituras forçadamente
     * stale: 5 requisições consecutivas onde cada uma ENXERGA o contador
     * em 0 (porque o cache retorna valor stale via mock) devem TODAS
     * resultar em contador final = 5, não em 1 (que seria o efeito do bug
     * get+put onde cada uma grava `0+1`).
     */
    public function test_stale_reads_cannot_defeat_atomic_counter(): void
    {
        $counterKey = $this->throttleKey;
        $realStore = Cache::getStore();

        // Mock que força Cache::get() a retornar sempre 0 na chave do
        // counter (simula leitura stale concorrente).
        $spy = \Mockery::mock($realStore)->makePartial();
        $spy->shouldReceive('get')->andReturnUsing(function ($key) use ($realStore, $counterKey) {
            if ($key === $counterKey) {
                return 0; // <- stale read: controller sempre "vê" 0.
            }

            return $realStore->get($key);
        });

        Cache::swap(new \Illuminate\Cache\Repository($spy));

        // 5 falhas. Se o controller usar get+put → grava 1, 1, 1, 1, 1
        //  (cada uma lê 0 stale, grava 0+1=1) → valor real no storage = 1.
        // Se o controller usar increment → grava atomicamente 1,2,3,4,5
        //  (increment não depende de get) → valor real no storage = 5.
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => $this->email,
                'password' => 'wrong-stale-'.$i,
            ]);
        }

        // Bypass o mock para ler o valor REAL do storage.
        $realCount = (int) $realStore->get($counterKey);

        $this->assertSame(
            5,
            $realCount,
            "Contador real no storage deveria ser 5 após 5 falhas concorrentes com leituras stale. ".
            "Obtido: {$realCount}. Valor <5 indica bug TOCTOU (get+put) — atacante pode exceder o limite sob carga."
        );
    }

    /**
     * Cenário C — TTL da janela (15 min) expira corretamente: após a
     * expiração, o contador reseta e novas tentativas são permitidas.
     */
    public function test_counter_resets_after_ttl_window_expires(): void
    {
        // Bloqueia a conta.
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => $this->email,
                'password' => 'wrong-'.$i,
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/login', [
            'email' => $this->email,
            'password' => 'wrong-6',
        ])->assertStatus(429);

        // Simula expiração do TTL (janela de 15 min).
        Cache::forget($this->throttleKey);
        Cache::forget($this->throttleKey.':ttl');

        // Agora, a próxima falha NÃO deve ser 429 — deve ser 422
        // (credencial inválida), provando que o contador zerou.
        $response = $this->postJson('/api/v1/login', [
            'email' => $this->email,
            'password' => 'wrong-after-reset',
        ]);

        $this->assertNotEquals(429, $response->getStatusCode(), 'Após expiração da janela, contador deve resetar.');
        $response->assertStatus(422);
    }

    /**
     * Login bem-sucedido DEVE limpar o contador mesmo após falhas
     * parciais, preservando comportamento original.
     */
    public function test_successful_login_clears_throttle_counter(): void
    {
        // 3 falhas.
        for ($i = 1; $i <= 3; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => $this->email,
                'password' => 'wrong-'.$i,
            ])->assertStatus(422);
        }

        // Login correto.
        $this->postJson('/api/v1/login', [
            'email' => $this->email,
            'password' => 'CorrectHorse!Battery9Staple',
        ])->assertOk();

        $this->assertNull(Cache::get($this->throttleKey), 'Contador deve ser limpo após login bem-sucedido.');
    }
}
