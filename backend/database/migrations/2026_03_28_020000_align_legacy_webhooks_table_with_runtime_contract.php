<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('webhooks')) {
            return;
        }

        Schema::table('webhooks', function (Blueprint $table) {
            if (! Schema::hasColumn('webhooks', 'name')) {
                $table->string('name')->default('Webhook')->after('tenant_id');
            }

            if (! Schema::hasColumn('webhooks', 'events')) {
                $table->json('events')->nullable()->after('event');
            }

            if (! Schema::hasColumn('webhooks', 'failure_count')) {
                $table->integer('failure_count')->default(0)->after('is_active');
            }

            if (! Schema::hasColumn('webhooks', 'last_triggered_at')) {
                $table->timestamp('last_triggered_at')->nullable()->after('failure_count');
            }

            if (! Schema::hasColumn('webhooks', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        if (Schema::hasColumn('webhooks', 'event') && Schema::hasColumn('webhooks', 'events')) {
            DB::table('webhooks')
                ->whereNull('events')
                ->orderBy('id')
                ->chunkById(100, function ($rows): void {
                    foreach ($rows as $row) {
                        $legacyEvent = is_string($row->event ?? null) ? $row->event : null;

                        DB::table('webhooks')
                            ->where('id', $row->id)
                            ->update([
                                'events' => json_encode(
                                    $legacyEvent ? [$this->normalizeWebhookEvent($legacyEvent)] : [],
                                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                                ),
                            ]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('webhooks')) {
            return;
        }

        Schema::table('webhooks', function (Blueprint $table) {
            if (Schema::hasColumn('webhooks', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            if (Schema::hasColumn('webhooks', 'last_triggered_at')) {
                $table->dropColumn('last_triggered_at');
            }

            if (Schema::hasColumn('webhooks', 'failure_count')) {
                $table->dropColumn('failure_count');
            }

            if (Schema::hasColumn('webhooks', 'events')) {
                $table->dropColumn('events');
            }

            if (Schema::hasColumn('webhooks', 'name')) {
                $table->dropColumn('name');
            }
        });
    }

    private function normalizeWebhookEvent(string $event): string
    {
        return match ($event) {
            'os.created' => 'work_order.created',
            'os.completed' => 'work_order.completed',
            'os.cancelled' => 'work_order.cancelled',
            default => $event,
        };
    }
};
