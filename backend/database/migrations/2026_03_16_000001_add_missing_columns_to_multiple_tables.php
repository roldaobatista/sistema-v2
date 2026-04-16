<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. quotes — missing created_by
        if (Schema::hasTable('quotes') && ! Schema::hasColumn('quotes', 'created_by')) {
            Schema::table('quotes', function (Blueprint $table) {
                $table->unsignedBigInteger('created_by')->nullable();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        // 2. products — missing max_stock, default_supplier_id
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'max_stock')) {
                    $table->decimal('max_stock', 15, 2)->nullable();
                }
                if (! Schema::hasColumn('products', 'default_supplier_id')) {
                    $table->unsignedBigInteger('default_supplier_id')->nullable();
                    $table->foreign('default_supplier_id')->references('id')->on('suppliers')->nullOnDelete();
                }
            });
        }

        // 3. ecological_disposals — missing status, created_by (disposed_by already exists)
        if (Schema::hasTable('ecological_disposals')) {
            Schema::table('ecological_disposals', function (Blueprint $table) {
                if (! Schema::hasColumn('ecological_disposals', 'status')) {
                    $table->string('status')->default('pending');
                }
                if (! Schema::hasColumn('ecological_disposals', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable();
                    $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                }
            });
        }

        // 4. purchase_quotations — missing reference, total_amount, requested_by, approved_by, approved_at
        if (Schema::hasTable('purchase_quotations')) {
            Schema::table('purchase_quotations', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_quotations', 'reference')) {
                    $table->string('reference')->nullable();
                }
                if (! Schema::hasColumn('purchase_quotations', 'total_amount')) {
                    $table->decimal('total_amount', 12, 2)->default(0);
                }
                if (! Schema::hasColumn('purchase_quotations', 'requested_by')) {
                    $table->unsignedBigInteger('requested_by')->nullable();
                    $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('purchase_quotations', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable();
                    $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('purchase_quotations', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable();
                }
            });
        }

        // 5. webhook_configs — missing last_triggered_at, failure_count
        if (Schema::hasTable('webhook_configs')) {
            Schema::table('webhook_configs', function (Blueprint $table) {
                if (! Schema::hasColumn('webhook_configs', 'last_triggered_at')) {
                    $table->timestamp('last_triggered_at')->nullable();
                }
                if (! Schema::hasColumn('webhook_configs', 'failure_count')) {
                    $table->unsignedInteger('failure_count')->default(0);
                }
            });
        }

        // 6. supplier_contracts — missing title, alert_days_before
        if (Schema::hasTable('supplier_contracts')) {
            Schema::table('supplier_contracts', function (Blueprint $table) {
                if (! Schema::hasColumn('supplier_contracts', 'title')) {
                    $table->string('title')->nullable();
                }
                if (! Schema::hasColumn('supplier_contracts', 'alert_days_before')) {
                    $table->unsignedInteger('alert_days_before')->nullable();
                }
            });
        }

        // 7. serial_numbers — missing batch_number, location
        if (Schema::hasTable('serial_numbers')) {
            Schema::table('serial_numbers', function (Blueprint $table) {
                if (! Schema::hasColumn('serial_numbers', 'batch_number')) {
                    $table->string('batch_number', 100)->nullable();
                }
                if (! Schema::hasColumn('serial_numbers', 'location')) {
                    $table->string('location')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // 1. quotes
        if (Schema::hasTable('quotes') && Schema::hasColumn('quotes', 'created_by')) {
            Schema::table('quotes', function (Blueprint $table) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            });
        }

        // 2. products
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'default_supplier_id')) {
                    $table->dropForeign(['default_supplier_id']);
                    $table->dropColumn('default_supplier_id');
                }
                if (Schema::hasColumn('products', 'max_stock')) {
                    $table->dropColumn('max_stock');
                }
            });
        }

        // 3. ecological_disposals
        if (Schema::hasTable('ecological_disposals')) {
            Schema::table('ecological_disposals', function (Blueprint $table) {
                if (Schema::hasColumn('ecological_disposals', 'created_by')) {
                    $table->dropForeign(['created_by']);
                    $table->dropColumn('created_by');
                }
                if (Schema::hasColumn('ecological_disposals', 'status')) {
                    $table->dropColumn('status');
                }
            });
        }

        // 4. purchase_quotations
        if (Schema::hasTable('purchase_quotations')) {
            Schema::table('purchase_quotations', function (Blueprint $table) {
                foreach (['requested_by', 'approved_by'] as $col) {
                    if (Schema::hasColumn('purchase_quotations', $col)) {
                        $table->dropForeign([$col]);
                    }
                }
                $cols = ['reference', 'total_amount', 'requested_by', 'approved_by', 'approved_at'];
                $existing = array_filter($cols, fn ($c) => Schema::hasColumn('purchase_quotations', $c));
                if ($existing) {
                    $table->dropColumn($existing);
                }
            });
        }

        // 5. webhook_configs
        if (Schema::hasTable('webhook_configs')) {
            Schema::table('webhook_configs', function (Blueprint $table) {
                $cols = ['last_triggered_at', 'failure_count'];
                $existing = array_filter($cols, fn ($c) => Schema::hasColumn('webhook_configs', $c));
                if ($existing) {
                    $table->dropColumn($existing);
                }
            });
        }

        // 6. supplier_contracts
        if (Schema::hasTable('supplier_contracts')) {
            Schema::table('supplier_contracts', function (Blueprint $table) {
                $cols = ['title', 'alert_days_before'];
                $existing = array_filter($cols, fn ($c) => Schema::hasColumn('supplier_contracts', $c));
                if ($existing) {
                    $table->dropColumn($existing);
                }
            });
        }

        // 7. serial_numbers
        if (Schema::hasTable('serial_numbers')) {
            Schema::table('serial_numbers', function (Blueprint $table) {
                $cols = ['batch_number', 'location'];
                $existing = array_filter($cols, fn ($c) => Schema::hasColumn('serial_numbers', $c));
                if ($existing) {
                    $table->dropColumn($existing);
                }
            });
        }
    }
};
