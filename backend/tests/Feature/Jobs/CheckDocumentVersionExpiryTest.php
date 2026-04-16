<?php

namespace Tests\Feature\Jobs;

use App\Jobs\CheckDocumentVersionExpiry;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckDocumentVersionExpiryTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
    }

    private function createDocumentVersion(array $overrides = []): int
    {
        return DB::table('document_versions')->insertGetId(array_merge([
            'tenant_id' => $this->tenant->id,
            'document_code' => 'DOC-'.str_pad((string) rand(1, 99999), 5, '0', STR_PAD_LEFT),
            'title' => 'Procedimento de Calibração',
            'category' => 'procedure',
            'version' => '1',
            'status' => 'approved',
            'created_by' => $this->user->id,
            'review_date' => now()->addDays(30)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    public function test_creates_notification_for_document_expiring_in_30_days(): void
    {
        $docId = $this->createDocumentVersion([
            'review_date' => now()->addDays(30)->toDateString(),
        ]);

        (new CheckDocumentVersionExpiry)->handle();

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant->id,
            'type' => 'document_expiring',
            'notifiable_type' => 'App\\Models\\DocumentVersion',
            'notifiable_id' => $docId,
        ]);
    }

    public function test_creates_notification_for_document_expiring_in_15_days(): void
    {
        $docId = $this->createDocumentVersion([
            'review_date' => now()->addDays(15)->toDateString(),
        ]);

        (new CheckDocumentVersionExpiry)->handle();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $docId,
            'type' => 'document_expiring',
        ]);
    }

    public function test_creates_notification_for_document_expiring_in_7_days(): void
    {
        $docId = $this->createDocumentVersion([
            'review_date' => now()->addDays(7)->toDateString(),
        ]);

        (new CheckDocumentVersionExpiry)->handle();

        $notification = DB::table('notifications')
            ->where('notifiable_id', $docId)
            ->where('type', 'document_expiring')
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('red', $notification->color);
    }

    public function test_does_not_notify_for_obsolete_documents(): void
    {
        $docId = $this->createDocumentVersion([
            'review_date' => now()->addDays(30)->toDateString(),
            'status' => 'obsolete',
        ]);

        (new CheckDocumentVersionExpiry)->handle();

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $docId,
            'type' => 'document_expiring',
        ]);
    }

    public function test_does_not_notify_for_documents_without_review_date(): void
    {
        $this->createDocumentVersion([
            'review_date' => null,
        ]);

        $countBefore = DB::table('notifications')->where('type', 'document_expiring')->count();

        (new CheckDocumentVersionExpiry)->handle();

        $countAfter = DB::table('notifications')->where('type', 'document_expiring')->count();
        $this->assertEquals($countBefore, $countAfter);
    }

    public function test_does_not_notify_for_documents_expiring_outside_thresholds(): void
    {
        $docId = $this->createDocumentVersion([
            'review_date' => now()->addDays(45)->toDateString(),
        ]);

        (new CheckDocumentVersionExpiry)->handle();

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $docId,
            'type' => 'document_expiring',
        ]);
    }

    public function test_does_not_notify_for_soft_deleted_documents(): void
    {
        $docId = $this->createDocumentVersion([
            'review_date' => now()->addDays(30)->toDateString(),
            'deleted_at' => now(),
        ]);

        (new CheckDocumentVersionExpiry)->handle();

        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $docId,
            'type' => 'document_expiring',
        ]);
    }

    public function test_notification_color_is_yellow_for_30_days(): void
    {
        $docId = $this->createDocumentVersion([
            'review_date' => now()->addDays(30)->toDateString(),
        ]);

        (new CheckDocumentVersionExpiry)->handle();

        $notification = DB::table('notifications')
            ->where('notifiable_id', $docId)
            ->where('type', 'document_expiring')
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('yellow', $notification->color);
    }

    public function test_notification_color_is_orange_for_15_days(): void
    {
        $docId = $this->createDocumentVersion([
            'review_date' => now()->addDays(15)->toDateString(),
        ]);

        (new CheckDocumentVersionExpiry)->handle();

        $notification = DB::table('notifications')
            ->where('notifiable_id', $docId)
            ->where('type', 'document_expiring')
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('orange', $notification->color);
    }
}
