<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\SettingGroup;
use App\Enums\SettingType;
use App\Http\Middleware\CheckPermission;
use App\Models\AuditLog;
use App\Models\NumberingSequence;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfigSystemDeepAuditTest extends TestCase
{
    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $adminA;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->tenantA = Tenant::factory()->create(['name' => 'Config Tenant A', 'status' => 'active']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Config Tenant B', 'status' => 'active']);

        $this->adminA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'current_tenant_id' => $this->tenantA->id,
            'email' => 'admin-a@config.test',
            'password' => Hash::make('Test1234!'),
            'is_active' => true,
        ]);
        $this->adminA->tenants()->attach($this->tenantA->id, ['is_default' => true]);

        $this->adminB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'current_tenant_id' => $this->tenantB->id,
            'email' => 'admin-b@config.test',
            'password' => Hash::make('Test1234!'),
            'is_active' => true,
        ]);
        $this->adminB->tenants()->attach($this->tenantB->id, ['is_default' => true]);

        $this->withoutMiddleware(CheckPermission::class);
        app()->instance('current_tenant_id', $this->tenantA->id);
    }

    // ══════════════════════════════════════════════
    // ── SYSTEM SETTINGS CRUD
    // ══════════════════════════════════════════════

    public function test_list_system_settings(): void
    {
        SystemSetting::setValue('color_theme', 'dark');
        SystemSetting::setValue('language', 'pt-BR');

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson('/api/v1/settings');

        $response->assertOk();
        $keys = collect($response->json('data'))->pluck('key');
        $this->assertTrue($keys->contains('color_theme'));
        $this->assertTrue($keys->contains('language'));
    }

    public function test_list_system_settings_filter_by_group(): void
    {
        SystemSetting::setValue('os_auto_number', 'true', SettingType::BOOLEAN, SettingGroup::OS);
        SystemSetting::setValue('company_name', 'Kalibrium', SettingType::STRING, SettingGroup::GENERAL);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson('/api/v1/settings?group=os');

        $response->assertOk();
        $groups = collect($response->json('data'))->pluck('group')->unique();
        $this->assertCount(1, $groups);
        $this->assertEquals('os', $groups->first());
    }

    public function test_update_system_settings(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->putJson('/api/v1/settings', [
            'settings' => [
                ['key' => 'company_name', 'value' => 'Kalibrium'],
                ['key' => 'os_auto_number', 'value' => true, 'type' => 'boolean'],
            ],
        ]);

        $response->assertOk();
        $this->assertEquals('Kalibrium', SystemSetting::getValue('company_name'));
    }

    public function test_update_settings_validates_quote_sequence_start(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->putJson('/api/v1/settings', [
            'settings' => [
                ['key' => 'quote_sequence_start', 'value' => 0],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_update_settings_validates_required_array(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->putJson('/api/v1/settings', [
            'settings' => [],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('settings');
    }

    public function test_update_settings_validates_key_required(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->putJson('/api/v1/settings', [
            'settings' => [
                ['value' => 'sem chave'],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_system_setting_typed_value_boolean(): void
    {
        SystemSetting::setValue('feature_flag', '1', SettingType::BOOLEAN);

        $this->assertTrue(SystemSetting::getValue('feature_flag'));
    }

    public function test_system_setting_typed_value_integer(): void
    {
        SystemSetting::setValue('max_items', '50', SettingType::INTEGER);

        $this->assertSame(50, SystemSetting::getValue('max_items'));
    }

    public function test_system_setting_typed_value_json(): void
    {
        $config = ['theme' => 'dark', 'sidebar' => true];
        SystemSetting::setValue('ui_config', $config, SettingType::JSON);

        $retrieved = SystemSetting::getValue('ui_config');
        $this->assertIsArray($retrieved);
        $this->assertEquals('dark', $retrieved['theme']);
    }

    public function test_system_setting_upsert_updates_existing(): void
    {
        SystemSetting::setValue('company_name', 'Old Name');
        SystemSetting::setValue('company_name', 'New Name');

        $count = SystemSetting::where('key', 'company_name')->count();
        $this->assertEquals(1, $count);
        $this->assertEquals('New Name', SystemSetting::getValue('company_name'));
    }

    public function test_system_settings_tenant_isolation(): void
    {
        SystemSetting::setValue('company_name', 'Tenant A Corp');

        app()->instance('current_tenant_id', $this->tenantB->id);
        SystemSetting::setValue('company_name', 'Tenant B Corp');

        app()->instance('current_tenant_id', $this->tenantA->id);
        $this->assertEquals('Tenant A Corp', SystemSetting::getValue('company_name'));

        app()->instance('current_tenant_id', $this->tenantB->id);
        $this->assertEquals('Tenant B Corp', SystemSetting::getValue('company_name'));
    }

    public function test_system_setting_throws_without_tenant(): void
    {
        app()->forgetInstance('current_tenant_id');
        $this->app->offsetUnset('current_tenant_id');
        auth()->guard('web')->logout();

        $this->expectException(\RuntimeException::class);
        SystemSetting::setValue('orphan_key', 'value');
    }

    public function test_upload_logo(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->adminA, ['*']);

        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->postJson('/api/v1/settings/logo', [
            'logo' => $file,
        ]);

        $response->assertOk()->assertJsonStructure(['data' => ['url']]);
        $this->assertNotEmpty($response->json('data.url'));

        $url = $response->json('data.url');
        $storedPath = str_replace('/storage/', '', $url);
        $this->assertTrue(Storage::disk('public')->exists($storedPath));
    }

    public function test_upload_invalid_logo_file_fails(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->postJson('/api/v1/settings/logo', [
            'logo' => $file,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('logo');
    }

    public function test_upload_logo_replaces_old_file(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->adminA, ['*']);

        $file1 = UploadedFile::fake()->image('logo1.png', 200, 200);
        $this->postJson('/api/v1/settings/logo', ['logo' => $file1])->assertOk();

        $file2 = UploadedFile::fake()->image('logo2.png', 200, 200);
        $response = $this->postJson('/api/v1/settings/logo', ['logo' => $file2]);

        $response->assertOk();
        $allFiles = Storage::disk('public')->allFiles("tenants/{$this->tenantA->id}/logo");
        $this->assertCount(1, $allFiles);
    }

    // ══════════════════════════════════════════════
    // ── TENANT SETTINGS CRUD
    // ══════════════════════════════════════════════

    public function test_tenant_settings_index(): void
    {
        TenantSetting::setValue($this->tenantA->id, 'theme', 'dark');
        TenantSetting::setValue($this->tenantA->id, 'language', 'pt-BR');

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson('/api/v1/tenant-settings');

        $response->assertOk();
        $data = $response->json();
        $this->assertEquals('dark', $data['theme']);
        $this->assertEquals('pt-BR', $data['language']);
    }

    public function test_tenant_settings_show(): void
    {
        TenantSetting::setValue($this->tenantA->id, 'email_footer', 'Obrigado!');

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson('/api/v1/tenant-settings/email_footer');

        $response->assertOk();
        $this->assertEquals('email_footer', $response->json('data.key'));
        $this->assertEquals('Obrigado!', $response->json('data.value'));
    }

    public function test_tenant_settings_upsert(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/tenant-settings', [
            'settings' => [
                ['key' => 'default_currency', 'value' => 'BRL'],
                ['key' => 'fiscal_regime', 'value' => 'simples'],
            ],
        ]);

        $response->assertOk();
        $data = $response->json();
        $this->assertEquals('BRL', $data['default_currency']);
        $this->assertEquals('simples', $data['fiscal_regime']);
    }

    public function test_tenant_settings_upsert_validates_required(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->postJson('/api/v1/tenant-settings', []);

        $response->assertStatus(422)->assertJsonValidationErrors('settings');
    }

    public function test_tenant_settings_destroy(): void
    {
        TenantSetting::setValue($this->tenantA->id, 'temp_config', 'to_delete');

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->deleteJson('/api/v1/tenant-settings/temp_config');

        $response->assertNoContent();

        $this->assertNull(TenantSetting::getValue($this->tenantA->id, 'temp_config'));
    }

    public function test_tenant_settings_destroy_nonexistent_returns_404(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->deleteJson('/api/v1/tenant-settings/nao_existe');

        $response->assertNotFound();
    }

    public function test_tenant_settings_isolation(): void
    {

        TenantSetting::setValue($this->tenantA->id, 'fiscal_regime', 'lucro_real');
        TenantSetting::setValue($this->tenantB->id, 'fiscal_regime', 'simples');

        $this->assertEquals('lucro_real', TenantSetting::getValue($this->tenantA->id, 'fiscal_regime'));
        $this->assertEquals('simples', TenantSetting::getValue($this->tenantB->id, 'fiscal_regime'));
    }

    // ══════════════════════════════════════════════
    // ── NUMBERING SEQUENCES
    // ══════════════════════════════════════════════

    public function test_list_numbering_sequences(): void
    {
        NumberingSequence::create([
            'tenant_id' => $this->tenantA->id,
            'entity' => 'work_orders',
            'prefix' => 'OS-',
            'next_number' => 1,
            'padding' => 5,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson('/api/v1/numbering-sequences');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json()));
    }

    public function test_update_numbering_sequence(): void
    {
        $seq = NumberingSequence::create([
            'tenant_id' => $this->tenantA->id,
            'entity' => 'quotes',
            'prefix' => 'ORC-',
            'next_number' => 100,
            'padding' => 4,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->putJson("/api/v1/numbering-sequences/{$seq->id}", [
            'prefix' => 'Q-',
            'next_number' => 500,
        ]);

        $response->assertOk();
        $seq->refresh();
        $this->assertEquals('Q-', $seq->prefix);
        $this->assertEquals(500, $seq->next_number);
    }

    public function test_update_numbering_sequence_other_tenant_returns_404(): void
    {
        $seqB = NumberingSequence::create([
            'tenant_id' => $this->tenantB->id,
            'entity' => 'invoices',
            'prefix' => 'NF-',
            'next_number' => 1,
            'padding' => 6,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->putJson("/api/v1/numbering-sequences/{$seqB->id}", [
            'prefix' => 'HACK-',
        ]);

        $response->assertNotFound();
    }

    public function test_preview_numbering_sequence(): void
    {
        $seq = NumberingSequence::create([
            'tenant_id' => $this->tenantA->id,
            'entity' => 'work_orders',
            'prefix' => 'OS-',
            'next_number' => 42,
            'padding' => 5,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);

        $response = $this->getJson("/api/v1/numbering-sequences/{$seq->id}/preview");

        $response->assertOk()->assertJsonPath('data.preview', 'OS-00042');
    }

    public function test_generate_next_number_atomically(): void
    {
        $seq = NumberingSequence::create([
            'tenant_id' => $this->tenantA->id,
            'entity' => 'test_entity',
            'prefix' => 'T-',
            'next_number' => 1,
            'padding' => 3,
        ]);

        $number1 = $seq->generateNext();
        $number2 = $seq->generateNext();

        $this->assertEquals('T-001', $number1);
        $this->assertEquals('T-002', $number2);
        $this->assertEquals(3, $seq->fresh()->next_number);
    }

    // ══════════════════════════════════════════════
    // ── AUDIT LOGS
    // ══════════════════════════════════════════════

    public function test_list_audit_logs(): void
    {
        AuditLog::log(AuditAction::CREATED, 'Test log entry A');

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson('/api/v1/audit-logs');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_audit_log_accepts_enum_and_string(): void
    {
        $logEnum = AuditLog::log(AuditAction::CREATED, 'Via enum');
        $logString = AuditLog::log('updated', 'Via string');

        $this->assertNotNull($logEnum->id);
        $this->assertNotNull($logString->id);
        $this->assertInstanceOf(AuditAction::class, $logEnum->action);
        $this->assertInstanceOf(AuditAction::class, $logString->action);
    }

    public function test_show_audit_log_with_diff(): void
    {
        $log = AuditLog::forceCreate([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->adminA->id,
            'action' => AuditAction::UPDATED,
            'description' => 'Empresa atualizada',
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => ['name' => 'Old Name'],
            'new_values' => ['name' => 'New Name'],
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson("/api/v1/audit-logs/{$log->id}");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['diff']]);

        $diff = $response->json('data.diff');
        $this->assertCount(1, $diff);
        $this->assertEquals('name', $diff[0]['field']);
        $this->assertEquals('Old Name', $diff[0]['old']);
        $this->assertEquals('New Name', $diff[0]['new']);
    }

    public function test_show_audit_log_from_other_tenant_returns_404(): void
    {
        $logB = AuditLog::forceCreate([
            'tenant_id' => $this->tenantB->id,
            'user_id' => $this->adminB->id,
            'action' => AuditAction::CREATED,
            'description' => 'Secret tenant B log',
            'auditable_type' => null,
            'auditable_id' => null,
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson("/api/v1/audit-logs/{$logB->id}");

        $response->assertNotFound();
    }

    public function test_audit_log_actions_list(): void
    {
        AuditLog::forceCreate([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->adminA->id,
            'action' => AuditAction::CREATED,
            'description' => 'Test',
        ]);
        AuditLog::forceCreate([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->adminA->id,
            'action' => AuditAction::DELETED,
            'description' => 'Test2',
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson('/api/v1/audit-logs/actions');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_audit_log_entity_types(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson('/api/v1/audit-logs/entity-types');
        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_audit_log_filter_by_action(): void
    {
        AuditLog::forceCreate([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->adminA->id,
            'action' => AuditAction::CREATED,
            'description' => 'Created something',
        ]);
        AuditLog::forceCreate([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->adminA->id,
            'action' => AuditAction::DELETED,
            'description' => 'Deleted something',
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson('/api/v1/audit-logs?action=created');

        $response->assertOk();
        $actions = collect($response->json('data'))->pluck('action')->unique();
        $this->assertTrue($actions->contains('created'));
        $this->assertFalse($actions->contains('deleted'));
    }

    public function test_audit_log_filter_by_date_range(): void
    {
        AuditLog::forceCreate([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->adminA->id,
            'action' => AuditAction::CREATED,
            'description' => 'Log today',
            'created_at' => now(),
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $from = now()->subDay()->toDateString();
        $to = now()->addDay()->toDateString();

        $response = $this->getJson("/api/v1/audit-logs?from={$from}&to={$to}");

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_audit_log_filter_by_search(): void
    {
        AuditLog::forceCreate([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->adminA->id,
            'action' => AuditAction::UPDATED,
            'description' => 'Atualizado cliente XPTO Industrial',
        ]);

        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson('/api/v1/audit-logs?search=XPTO');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    // ══════════════════════════════════════════════
    // ── SECURITY: PASSWORD POLICY
    // ══════════════════════════════════════════════

    public function test_get_default_password_policy(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson('/api/v1/security/password-policy');

        $response->assertOk()->assertJsonStructure(['data']);
        $data = $response->json('data');
        $this->assertEquals(8, $data['min_length']);
        $this->assertTrue($data['require_uppercase']);
    }

    // ══════════════════════════════════════════════
    // ── SECURITY: WATERMARK CONFIG
    // ══════════════════════════════════════════════

    public function test_get_default_watermark_config(): void
    {
        Sanctum::actingAs($this->adminA, ['*']);
        $response = $this->getJson('/api/v1/security/watermark');

        $response->assertOk()->assertJsonStructure(['data']);
        $this->assertFalse($response->json('data.enabled'));
    }

    // ══════════════════════════════════════════════
    // ── ENUMS UNIT VALIDATION
    // ══════════════════════════════════════════════

    public function test_setting_group_enum_has_all_values(): void
    {
        $cases = SettingGroup::cases();
        $this->assertCount(8, $cases);
        $this->assertNotNull(SettingGroup::tryFrom('general'));
        $this->assertNotNull(SettingGroup::tryFrom('os'));
        $this->assertNotNull(SettingGroup::tryFrom('crm'));
        $this->assertNull(SettingGroup::tryFrom('inexistente'));
    }

    public function test_setting_type_enum_has_all_values(): void
    {
        $cases = SettingType::cases();
        $this->assertCount(4, $cases);
        $this->assertNotNull(SettingType::tryFrom('string'));
        $this->assertNotNull(SettingType::tryFrom('boolean'));
        $this->assertNotNull(SettingType::tryFrom('json'));
    }

    public function test_audit_action_enum_labels(): void
    {
        $this->assertEquals('Criado', AuditAction::CREATED->label());
        $this->assertEquals('Excluído', AuditAction::DELETED->label());
        $this->assertEquals('Troca de Empresa', AuditAction::TENANT_SWITCH->label());
    }
}
