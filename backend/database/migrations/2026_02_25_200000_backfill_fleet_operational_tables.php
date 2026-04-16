<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureFuelLogs();
        $this->ensureVehicleTires();
        $this->ensureVehiclePoolRequests();
        $this->ensureVehicleAccidents();
    }

    public function down(): void
    {
        $this->dropIndexIfExists('fuel_logs', 'fuel_logs_tenant_date_idx');
        $this->dropIndexIfExists('fuel_logs', 'fuel_logs_vehicle_date_idx');
        $this->dropColumnsIfExists('fuel_logs', [
            'tenant_id',
            'fleet_vehicle_id',
            'driver_id',
            'date',
            'odometer_km',
            'liters',
            'price_per_liter',
            'total_value',
            'fuel_type',
            'gas_station',
            'consumption_km_l',
            'receipt_path',
        ]);

        $this->dropIndexIfExists('vehicle_tires', 'vehicle_tires_tenant_status_idx');
        $this->dropIndexIfExists('vehicle_tires', 'vehicle_tires_vehicle_idx');
        $this->dropColumnsIfExists('vehicle_tires', [
            'tenant_id',
            'fleet_vehicle_id',
            'serial_number',
            'brand',
            'model',
            'position',
            'tread_depth',
            'retread_count',
            'installed_at',
            'installed_km',
            'status',
            'deleted_at',
        ]);

        $this->dropIndexIfExists('vehicle_pool_requests', 'vehicle_pool_tenant_status_idx');
        $this->dropIndexIfExists('vehicle_pool_requests', 'vehicle_pool_user_idx');
        $this->dropColumnsIfExists('vehicle_pool_requests', [
            'tenant_id',
            'user_id',
            'fleet_vehicle_id',
            'requested_start',
            'requested_end',
            'actual_start',
            'actual_end',
            'purpose',
            'status',
        ]);

        $this->dropIndexIfExists('vehicle_accidents', 'vehicle_accidents_tenant_status_idx');
        $this->dropIndexIfExists('vehicle_accidents', 'vehicle_accidents_vehicle_date_idx');
        $this->dropColumnsIfExists('vehicle_accidents', [
            'tenant_id',
            'fleet_vehicle_id',
            'driver_id',
            'occurrence_date',
            'location',
            'description',
            'third_party_involved',
            'third_party_info',
            'police_report_number',
            'photos',
            'estimated_cost',
            'status',
        ]);
    }

    private function ensureFuelLogs(): void
    {
        if (! Schema::hasTable('fuel_logs')) {
            Schema::create('fuel_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('fleet_vehicle_id')->nullable();
                $table->unsignedBigInteger('driver_id')->nullable();
                $table->date('date')->nullable();
                $table->integer('odometer_km')->nullable();
                $table->decimal('liters', 10, 3)->nullable();
                $table->decimal('price_per_liter', 12, 4)->nullable();
                $table->decimal('total_value', 12, 2)->nullable();
                $table->string('fuel_type', 50)->nullable();
                $table->string('gas_station', 255)->nullable();
                $table->decimal('consumption_km_l', 10, 3)->nullable();
                $table->string('receipt_path')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('fuel_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('fuel_logs', 'tenant_id')) {
                    $table->unsignedBigInteger('tenant_id')->nullable();
                }
                if (! Schema::hasColumn('fuel_logs', 'fleet_vehicle_id')) {
                    $table->unsignedBigInteger('fleet_vehicle_id')->nullable();
                }
                if (! Schema::hasColumn('fuel_logs', 'driver_id')) {
                    $table->unsignedBigInteger('driver_id')->nullable();
                }
                if (! Schema::hasColumn('fuel_logs', 'date')) {
                    $table->date('date')->nullable();
                }
                if (! Schema::hasColumn('fuel_logs', 'odometer_km')) {
                    $table->integer('odometer_km')->nullable();
                }
                if (! Schema::hasColumn('fuel_logs', 'liters')) {
                    $table->decimal('liters', 10, 3)->nullable();
                }
                if (! Schema::hasColumn('fuel_logs', 'price_per_liter')) {
                    $table->decimal('price_per_liter', 12, 4)->nullable();
                }
                if (! Schema::hasColumn('fuel_logs', 'total_value')) {
                    $table->decimal('total_value', 12, 2)->nullable();
                }
                if (! Schema::hasColumn('fuel_logs', 'fuel_type')) {
                    $table->string('fuel_type', 50)->nullable();
                }
                if (! Schema::hasColumn('fuel_logs', 'gas_station')) {
                    $table->string('gas_station', 255)->nullable();
                }
                if (! Schema::hasColumn('fuel_logs', 'consumption_km_l')) {
                    $table->decimal('consumption_km_l', 10, 3)->nullable();
                }
                if (! Schema::hasColumn('fuel_logs', 'receipt_path')) {
                    $table->string('receipt_path')->nullable();
                }
            });
        }

        $this->addIndexIfPossible('fuel_logs', ['tenant_id', 'date'], 'fuel_logs_tenant_date_idx');
        $this->addIndexIfPossible('fuel_logs', ['fleet_vehicle_id', 'date'], 'fuel_logs_vehicle_date_idx');
    }

    private function ensureVehicleTires(): void
    {
        if (! Schema::hasTable('vehicle_tires')) {
            Schema::create('vehicle_tires', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('fleet_vehicle_id')->nullable();
                $table->string('serial_number')->nullable();
                $table->string('brand')->nullable();
                $table->string('model')->nullable();
                $table->string('position')->nullable();
                $table->decimal('tread_depth', 5, 2)->nullable();
                $table->integer('retread_count')->default(0);
                $table->date('installed_at')->nullable();
                $table->integer('installed_km')->nullable();
                $table->string('status', 30)->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('vehicle_tires', function (Blueprint $table) {
                if (! Schema::hasColumn('vehicle_tires', 'tenant_id')) {
                    $table->unsignedBigInteger('tenant_id')->nullable();
                }
                if (! Schema::hasColumn('vehicle_tires', 'fleet_vehicle_id')) {
                    $table->unsignedBigInteger('fleet_vehicle_id')->nullable();
                }
                if (! Schema::hasColumn('vehicle_tires', 'serial_number')) {
                    $table->string('serial_number')->nullable();
                }
                if (! Schema::hasColumn('vehicle_tires', 'brand')) {
                    $table->string('brand')->nullable();
                }
                if (! Schema::hasColumn('vehicle_tires', 'model')) {
                    $table->string('model')->nullable();
                }
                if (! Schema::hasColumn('vehicle_tires', 'position')) {
                    $table->string('position')->nullable();
                }
                if (! Schema::hasColumn('vehicle_tires', 'tread_depth')) {
                    $table->decimal('tread_depth', 5, 2)->nullable();
                }
                if (! Schema::hasColumn('vehicle_tires', 'retread_count')) {
                    $table->integer('retread_count')->default(0);
                }
                if (! Schema::hasColumn('vehicle_tires', 'installed_at')) {
                    $table->date('installed_at')->nullable();
                }
                if (! Schema::hasColumn('vehicle_tires', 'installed_km')) {
                    $table->integer('installed_km')->nullable();
                }
                if (! Schema::hasColumn('vehicle_tires', 'status')) {
                    $table->string('status', 30)->default('active');
                }
                if (! Schema::hasColumn('vehicle_tires', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        $this->addIndexIfPossible('vehicle_tires', ['tenant_id', 'status'], 'vehicle_tires_tenant_status_idx');
        $this->addIndexIfPossible('vehicle_tires', ['fleet_vehicle_id'], 'vehicle_tires_vehicle_idx');
    }

    private function ensureVehiclePoolRequests(): void
    {
        if (! Schema::hasTable('vehicle_pool_requests')) {
            Schema::create('vehicle_pool_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('fleet_vehicle_id')->nullable();
                $table->timestamp('requested_start')->nullable();
                $table->timestamp('requested_end')->nullable();
                $table->timestamp('actual_start')->nullable();
                $table->timestamp('actual_end')->nullable();
                $table->text('purpose')->nullable();
                $table->string('status', 30)->default('pending');
                $table->timestamps();
            });
        } else {
            Schema::table('vehicle_pool_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('vehicle_pool_requests', 'tenant_id')) {
                    $table->unsignedBigInteger('tenant_id')->nullable();
                }
                if (! Schema::hasColumn('vehicle_pool_requests', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable();
                }
                if (! Schema::hasColumn('vehicle_pool_requests', 'fleet_vehicle_id')) {
                    $table->unsignedBigInteger('fleet_vehicle_id')->nullable();
                }
                if (! Schema::hasColumn('vehicle_pool_requests', 'requested_start')) {
                    $table->timestamp('requested_start')->nullable();
                }
                if (! Schema::hasColumn('vehicle_pool_requests', 'requested_end')) {
                    $table->timestamp('requested_end')->nullable();
                }
                if (! Schema::hasColumn('vehicle_pool_requests', 'actual_start')) {
                    $table->timestamp('actual_start')->nullable();
                }
                if (! Schema::hasColumn('vehicle_pool_requests', 'actual_end')) {
                    $table->timestamp('actual_end')->nullable();
                }
                if (! Schema::hasColumn('vehicle_pool_requests', 'purpose')) {
                    $table->text('purpose')->nullable();
                }
                if (! Schema::hasColumn('vehicle_pool_requests', 'status')) {
                    $table->string('status', 30)->default('pending');
                }
            });
        }

        $this->addIndexIfPossible('vehicle_pool_requests', ['tenant_id', 'status'], 'vehicle_pool_tenant_status_idx');
        $this->addIndexIfPossible('vehicle_pool_requests', ['user_id'], 'vehicle_pool_user_idx');
    }

    private function ensureVehicleAccidents(): void
    {
        if (! Schema::hasTable('vehicle_accidents')) {
            Schema::create('vehicle_accidents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->unsignedBigInteger('fleet_vehicle_id')->nullable();
                $table->unsignedBigInteger('driver_id')->nullable();
                $table->date('occurrence_date')->nullable();
                $table->string('location')->nullable();
                $table->text('description')->nullable();
                $table->boolean('third_party_involved')->default(false);
                $table->text('third_party_info')->nullable();
                $table->string('police_report_number')->nullable();
                $table->json('photos')->nullable();
                $table->decimal('estimated_cost', 12, 2)->nullable();
                $table->string('status', 30)->default('investigating');
                $table->timestamps();
            });
        } else {
            Schema::table('vehicle_accidents', function (Blueprint $table) {
                if (! Schema::hasColumn('vehicle_accidents', 'tenant_id')) {
                    $table->unsignedBigInteger('tenant_id')->nullable();
                }
                if (! Schema::hasColumn('vehicle_accidents', 'fleet_vehicle_id')) {
                    $table->unsignedBigInteger('fleet_vehicle_id')->nullable();
                }
                if (! Schema::hasColumn('vehicle_accidents', 'driver_id')) {
                    $table->unsignedBigInteger('driver_id')->nullable();
                }
                if (! Schema::hasColumn('vehicle_accidents', 'occurrence_date')) {
                    $table->date('occurrence_date')->nullable();
                }
                if (! Schema::hasColumn('vehicle_accidents', 'location')) {
                    $table->string('location')->nullable();
                }
                if (! Schema::hasColumn('vehicle_accidents', 'description')) {
                    $table->text('description')->nullable();
                }
                if (! Schema::hasColumn('vehicle_accidents', 'third_party_involved')) {
                    $table->boolean('third_party_involved')->default(false);
                }
                if (! Schema::hasColumn('vehicle_accidents', 'third_party_info')) {
                    $table->text('third_party_info')->nullable();
                }
                if (! Schema::hasColumn('vehicle_accidents', 'police_report_number')) {
                    $table->string('police_report_number')->nullable();
                }
                if (! Schema::hasColumn('vehicle_accidents', 'photos')) {
                    $table->json('photos')->nullable();
                }
                if (! Schema::hasColumn('vehicle_accidents', 'estimated_cost')) {
                    $table->decimal('estimated_cost', 12, 2)->nullable();
                }
                if (! Schema::hasColumn('vehicle_accidents', 'status')) {
                    $table->string('status', 30)->default('investigating');
                }
            });
        }

        $this->addIndexIfPossible('vehicle_accidents', ['tenant_id', 'status'], 'vehicle_accidents_tenant_status_idx');
        $this->addIndexIfPossible('vehicle_accidents', ['fleet_vehicle_id', 'occurrence_date'], 'vehicle_accidents_vehicle_date_idx');
    }

    private function addIndexIfPossible(string $table, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
                $blueprint->index($columns, $indexName);
            });
        } catch (Throwable) {
            // Index already exists or unsupported by current driver.
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                $blueprint->dropIndex($indexName);
            });
        } catch (Throwable) {
            // Ignore when index does not exist.
        }
    }

    private function dropColumnsIfExists(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            try {
                Schema::table($table, function (Blueprint $blueprint) use ($column) {
                    $blueprint->dropColumn($column);
                });
            } catch (Throwable) {
                // Ignore when the driver cannot drop this column or constraints still exist.
            }
        }
    }
};
