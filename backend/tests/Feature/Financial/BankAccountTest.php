<?php

namespace Tests\Feature\Financial;

use App\Models\BankAccount;
use App\Models\FundTransfer;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BankAccountTest extends TestCase
{
    protected $user;

    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);

        // Create permissions required by check.permission middleware
        $permissions = [
            'financial.bank_account.view',
            'financial.bank_account.create',
            'financial.bank_account.update',
            'financial.bank_account.delete',
        ];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => 'web',
            ]);
        }

        // Create admin role with all permissions
        $role = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
            'tenant_id' => $this->tenant->id,
        ]);
        $role->syncPermissions($permissions);
        $this->user->assignRole($role);
    }

    public function test_can_list_bank_accounts(): void
    {
        BankAccount::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/bank-accounts')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_bank_account(): void
    {
        $data = [
            'name' => 'Conta Principal',
            'bank_name' => 'Banco do Brasil',
            'agency' => '1234',
            'account_number' => '56789-0',
            'account_type' => 'corrente',
            'balance' => 1000.00,
            'is_active' => true,
        ];

        $this->actingAs($this->user)
            ->postJson('/api/v1/bank-accounts', $data)
            ->assertCreated()
            ->assertJsonFragment(['name' => 'Conta Principal']);

        $this->assertDatabaseHas('bank_accounts', [
            'name' => 'Conta Principal',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_can_update_bank_account(): void
    {
        $account = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user)
            ->putJson("/api/v1/bank-accounts/{$account->id}", [
                'name' => 'Conta Atualizada',
                'bank_name' => $account->bank_name,
                'account_type' => $account->account_type,
            ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Conta Atualizada']);

        $this->assertDatabaseHas('bank_accounts', [
            'id' => $account->id,
            'name' => 'Conta Atualizada',
        ]);
    }

    public function test_can_delete_unused_bank_account(): void
    {
        $account = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/bank-accounts/{$account->id}");

        $response->assertOk();
    }

    public function test_cannot_delete_bank_account_with_transfers(): void
    {
        $account = BankAccount::factory()->create(['tenant_id' => $this->tenant->id]);
        FundTransfer::factory()->create([
            'bank_account_id' => $account->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'completed',
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/bank-accounts/{$account->id}")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Esta conta possui transferências ativas. Cancele-as antes de excluir.']);

        $this->assertDatabaseHas('bank_accounts', ['id' => $account->id]);
    }

    public function test_search_bank_accounts(): void
    {
        BankAccount::factory()->create(['name' => 'Conta Alpha', 'tenant_id' => $this->tenant->id]);
        BankAccount::factory()->create(['name' => 'Conta Beta', 'tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/bank-accounts?search=Alpha')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Conta Alpha']);
    }
}
