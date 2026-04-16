<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Migrate service_call statuses
        if (Schema::hasTable('service_calls')) {
            $statusMap = [
                'open' => 'pending_scheduling',
                'in_transit' => 'scheduled',
                'in_progress' => 'scheduled',
                'completed' => 'converted_to_os',
            ];

            foreach ($statusMap as $old => $new) {
                DB::table('service_calls')->where('status', $old)->update(['status' => $new]);
            }
        }

        // 2) Add execution columns to work_orders
        if (Schema::hasTable('work_orders')) {
            if (! Schema::hasColumn('work_orders', 'service_started_at')) {
                Schema::table('work_orders', function (Blueprint $table) {
                    $table->timestamp('service_started_at')->nullable();
                    $table->integer('wait_time_minutes')->nullable();
                    $table->integer('service_duration_minutes')->nullable();
                    $table->integer('total_duration_minutes')->nullable();
                    $table->decimal('arrival_latitude', 10, 8)->nullable();
                    $table->decimal('arrival_longitude', 11, 8)->nullable();
                    $table->string('service_type', 50)->nullable();
                    $table->text('manual_justification')->nullable();
                });
            }
        }

        // 3) Create work_order_events table
        if (! Schema::hasTable('work_order_events')) {
            Schema::create('work_order_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
                $table->string('event_type', 50);
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['work_order_id', 'created_at'], 'wo_events_wo_created_idx');
            });
        }

        // 4) Create customer_locations table
        if (! Schema::hasTable('customer_locations')) {
            Schema::create('customer_locations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->decimal('latitude', 10, 8);
                $table->decimal('longitude', 11, 8);
                $table->string('source', 30)->default('manual');
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('label', 200)->nullable();
                $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('customer_id', 'cust_loc_customer_idx');
            });
        }

        // 5) Migrate existing work_order 'in_progress' to 'in_service' if we later update status values
        // (This is handled at application level since status is a string field)
    }

    public function down(): void
    {
        // Revert service_call statuses
        if (Schema::hasTable('service_calls')) {
            $reverseMap = [
                'pending_scheduling' => 'open',
                'rescheduled' => 'scheduled',
                'awaiting_confirmation' => 'scheduled',
                'converted_to_os' => 'completed',
            ];

            foreach ($reverseMap as $new => $old) {
                DB::table('service_calls')->where('status', $new)->update(['status' => $old]);
            }
        }

        Schema::dropIfExists('customer_locations');
        Schema::dropIfExists('work_order_events');

        if (Schema::hasTable('work_orders')) {
            $columns = ['service_started_at', 'wait_time_minutes', 'service_duration_minutes',
                'total_duration_minutes', 'arrival_latitude', 'arrival_longitude',
                'service_type', 'manual_justification'];

            $existingColumns = array_filter($columns, fn ($col) => Schema::hasColumn('work_orders', $col));

            if (! empty($existingColumns)) {
                Schema::table('work_orders', function (Blueprint $table) use ($existingColumns) {
                    $table->dropColumn($existingColumns);
                });
            }
        }
    }
};
