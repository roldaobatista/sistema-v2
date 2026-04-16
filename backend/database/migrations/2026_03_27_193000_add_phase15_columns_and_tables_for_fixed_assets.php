<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asset_records')) {
            Schema::table('asset_records', function (Blueprint $table): void {
                if (! Schema::hasColumn('asset_records', 'crm_deal_id')) {
                    $table->foreignId('crm_deal_id')->nullable()->constrained('crm_deals')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table): void {
                if (! Schema::hasColumn('expenses', 'reference_type')) {
                    $table->string('reference_type', 50)->nullable();
                }

                if (! Schema::hasColumn('expenses', 'reference_id')) {
                    $table->unsignedBigInteger('reference_id')->nullable();
                }
            });
        }

        if (Schema::hasTable('accounts_receivable')) {
            Schema::table('accounts_receivable', function (Blueprint $table): void {
                if (! Schema::hasColumn('accounts_receivable', 'reference_id')) {
                    $table->unsignedBigInteger('reference_id')->nullable();
                }
            });
        }

        if (! Schema::hasTable('asset_movements')) {
            Schema::create('asset_movements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('asset_record_id')->constrained('asset_records')->cascadeOnDelete();
                $table->string('movement_type', 30);
                $table->string('from_location')->nullable();
                $table->string('to_location')->nullable();
                $table->foreignId('from_responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('to_responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('moved_at');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'asset_record_id'], 'asset_movements_tenant_asset_idx');
                $table->index(['tenant_id', 'movement_type'], 'asset_movements_tenant_type_idx');
            });
        }

        if (! Schema::hasTable('asset_inventories')) {
            Schema::create('asset_inventories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('asset_record_id')->constrained('asset_records')->cascadeOnDelete();
                $table->date('inventory_date');
                $table->string('counted_location')->nullable();
                $table->string('counted_status', 30)->nullable();
                $table->boolean('condition_ok')->default(true);
                $table->boolean('divergent')->default(false);
                $table->string('offline_reference', 100)->nullable();
                $table->boolean('synced_from_pwa')->default(false);
                $table->text('notes')->nullable();
                $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'asset_record_id'], 'asset_inventories_tenant_asset_idx');
                $table->index(['tenant_id', 'inventory_date'], 'asset_inventories_tenant_date_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('asset_inventories')) {
            Schema::dropIfExists('asset_inventories');
        }

        if (Schema::hasTable('asset_movements')) {
            Schema::dropIfExists('asset_movements');
        }

        if (Schema::hasTable('accounts_receivable') && Schema::hasColumn('accounts_receivable', 'reference_id')) {
            Schema::table('accounts_receivable', function (Blueprint $table): void {
                $table->dropColumn('reference_id');
            });
        }

        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table): void {
                if (Schema::hasColumn('expenses', 'reference_id')) {
                    $table->dropColumn('reference_id');
                }

                if (Schema::hasColumn('expenses', 'reference_type')) {
                    $table->dropColumn('reference_type');
                }
            });
        }

        if (Schema::hasTable('asset_records') && Schema::hasColumn('asset_records', 'crm_deal_id')) {
            Schema::table('asset_records', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('crm_deal_id');
            });
        }
    }
};
