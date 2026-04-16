<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Tests\Traits\DisablesTenantMiddleware;
use Tests\Traits\SetupTenantUser;

/**
 * Testa validação do StoreSupplierRequest (nome, tipo, email, dados válidos).
 */
class StoreSupplierRequestTest extends TestCase
{
    use DisablesTenantMiddleware;
    use SetupTenantUser;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->setUpDisablesTenantMiddleware();
        $this->setUpTenantUser();
    }

    public function test_requires_name(): void
    {
        $this->postJson('/api/v1/suppliers', [
            'type' => 'PJ',
            'name' => '',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_requires_type(): void
    {
        $this->postJson('/api/v1/suppliers', [
            'name' => 'Fornecedor Teste',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_validates_type_enum(): void
    {
        $this->postJson('/api/v1/suppliers', [
            'name' => 'Fornecedor Teste',
            'type' => 'INVALID',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_validates_email_format(): void
    {
        $this->postJson('/api/v1/suppliers', [
            'name' => 'Fornecedor Teste',
            'type' => 'PJ',
            'email' => 'not-an-email',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_accepts_valid_data(): void
    {
        $this->postJson('/api/v1/suppliers', [
            'name' => 'Fornecedor Teste',
            'type' => 'PJ',
            'email' => 'test@example.com',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Fornecedor Teste')
            ->assertJsonPath('data.type', 'PJ');
    }
}
