<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            // Colunas de identificação
            if (! Schema::hasColumn('equipments', 'code')) {
                $table->string('code', 50)->nullable()->after('customer_id');
            }
            if (! Schema::hasColumn('equipments', 'name')) {
                $table->string('name', 255)->nullable()->after('code');
            }
            if (! Schema::hasColumn('equipments', 'category')) {
                $table->string('category', 50)->nullable()->after('type');
            }
            if (! Schema::hasColumn('equipments', 'manufacturer')) {
                $table->string('manufacturer', 100)->nullable()->after('brand');
            }
            if (! Schema::hasColumn('equipments', 'status')) {
                $table->string('status', 30)->default('ativo')->after('notes');
            }

            // Colunas de capacidade/medição
            if (! Schema::hasColumn('equipments', 'capacity')) {
                $table->decimal('capacity', 14, 4)->nullable()->after('serial_number');
            }
            if (! Schema::hasColumn('equipments', 'capacity_unit')) {
                $table->string('capacity_unit', 20)->nullable()->after('capacity');
            }
            if (! Schema::hasColumn('equipments', 'resolution')) {
                $table->decimal('resolution', 14, 6)->nullable()->after('capacity_unit');
            }
            if (! Schema::hasColumn('equipments', 'precision_class')) {
                $table->string('precision_class', 10)->nullable()->after('resolution');
            }
            if (! Schema::hasColumn('equipments', 'manufacturing_date')) {
                $table->date('manufacturing_date')->nullable()->after('precision_class');
            }

            // Colunas de compra/garantia
            if (! Schema::hasColumn('equipments', 'purchase_date')) {
                $table->date('purchase_date')->nullable()->after('manufacturing_date');
            }
            if (! Schema::hasColumn('equipments', 'purchase_value')) {
                $table->decimal('purchase_value', 14, 2)->nullable()->after('purchase_date');
            }
            if (! Schema::hasColumn('equipments', 'warranty_expires_at')) {
                $table->date('warranty_expires_at')->nullable()->after('purchase_value');
            }

            // Colunas de calibração
            if (! Schema::hasColumn('equipments', 'last_calibration_at')) {
                $table->date('last_calibration_at')->nullable()->after('warranty_expires_at');
            }
            if (! Schema::hasColumn('equipments', 'next_calibration_at')) {
                $table->date('next_calibration_at')->nullable()->after('last_calibration_at');
            }
            if (! Schema::hasColumn('equipments', 'calibration_interval_months')) {
                $table->unsignedSmallInteger('calibration_interval_months')->nullable()->after('next_calibration_at');
            }

            // Responsável
            if (! Schema::hasColumn('equipments', 'responsible_user_id')) {
                $table->foreignId('responsible_user_id')->nullable()->after('location');
            }
        });
    }

    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $cols = [
                'code', 'name', 'category', 'manufacturer', 'status',
                'capacity', 'capacity_unit', 'resolution', 'precision_class',
                'manufacturing_date', 'purchase_date', 'purchase_value',
                'warranty_expires_at', 'last_calibration_at', 'next_calibration_at',
                'calibration_interval_months', 'responsible_user_id',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('equipments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
