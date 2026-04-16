<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidates all missing columns identified by running the full test suite.
 * Each column addition is guarded by hasColumn to be idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── commission_rules ──
        if (Schema::hasTable('commission_rules')) {
            Schema::table('commission_rules', function (Blueprint $t) {
                if (! Schema::hasColumn('commission_rules', 'percentage')) {
                    $t->decimal('percentage', 8, 2)->nullable();
                }
                if (! Schema::hasColumn('commission_rules', 'fixed_amount')) {
                    $t->decimal('fixed_amount', 12, 2)->nullable();
                }
            });
        }

        // ── commission_events ──
        if (Schema::hasTable('commission_events')) {
            Schema::table('commission_events', function (Blueprint $t) {
                if (! Schema::hasColumn('commission_events', 'amount')) {
                    $t->decimal('amount', 12, 2)->nullable();
                }
                if (! Schema::hasColumn('commission_events', 'proportion')) {
                    $t->decimal('proportion', 8, 4)->nullable();
                }
            });
        }

        // ── commission_settlements ──
        if (Schema::hasTable('commission_settlements')) {
            Schema::table('commission_settlements', function (Blueprint $t) {
                if (! Schema::hasColumn('commission_settlements', 'total')) {
                    $t->decimal('total', 12, 2)->nullable();
                }
                if (! Schema::hasColumn('commission_settlements', 'closed_by')) {
                    $t->unsignedBigInteger('closed_by')->nullable();
                }
                if (! Schema::hasColumn('commission_settlements', 'closed_at')) {
                    $t->timestamp('closed_at')->nullable();
                }
                if (! Schema::hasColumn('commission_settlements', 'approved_by')) {
                    $t->unsignedBigInteger('approved_by')->nullable();
                }
                if (! Schema::hasColumn('commission_settlements', 'approved_at')) {
                    $t->timestamp('approved_at')->nullable();
                }
            });
        }

        // ── commission_goals ──
        if (Schema::hasTable('commission_goals')) {
            Schema::table('commission_goals', function (Blueprint $t) {
                if (! Schema::hasColumn('commission_goals', 'target_value')) {
                    $t->decimal('target_value', 12, 2)->nullable();
                }
                if (! Schema::hasColumn('commission_goals', 'current_value')) {
                    $t->decimal('current_value', 12, 2)->nullable();
                }
                if (! Schema::hasColumn('commission_goals', 'type')) {
                    $t->string('type', 30)->nullable();
                }
            });
        }

        // ── commission_campaigns ──
        if (Schema::hasTable('commission_campaigns')) {
            Schema::table('commission_campaigns', function (Blueprint $t) {
                if (! Schema::hasColumn('commission_campaigns', 'is_active')) {
                    $t->boolean('is_active')->nullable();
                }
            });
        }

        // ── recurring_commissions ──
        if (Schema::hasTable('recurring_commissions')) {
            Schema::table('recurring_commissions', function (Blueprint $t) {
                if (! Schema::hasColumn('recurring_commissions', 'frequency')) {
                    $t->string('frequency', 20)->nullable();
                }
            });
        }

        // ── crm_pipeline_stages ──
        if (Schema::hasTable('crm_pipeline_stages')) {
            Schema::table('crm_pipeline_stages', function (Blueprint $t) {
                if (! Schema::hasColumn('crm_pipeline_stages', 'order')) {
                    $t->integer('order')->nullable();
                }
            });
        }

        // ── quotes ──
        if (Schema::hasTable('quotes')) {
            Schema::table('quotes', function (Blueprint $t) {
                if (! Schema::hasColumn('quotes', 'discount')) {
                    $t->decimal('discount', 12, 2)->nullable();
                }
                if (! Schema::hasColumn('quotes', 'validity_days')) {
                    $t->integer('validity_days')->nullable();
                }
            });
        }

        // ── surveys ──
        if (Schema::hasTable('surveys')) {
            Schema::table('surveys', function (Blueprint $t) {
                if (! Schema::hasColumn('surveys', 'questions')) {
                    $t->json('questions')->nullable();
                }
            });
        }

        // ── customers ──
        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $t) {
                if (! Schema::hasColumn('customers', 'company_name')) {
                    $t->string('company_name')->nullable();
                }
            });
        }

        // ── equipments ──
        if (Schema::hasTable('equipments')) {
            Schema::table('equipments', function (Blueprint $t) {
                if (! Schema::hasColumn('equipments', 'accuracy_class')) {
                    $t->string('accuracy_class', 10)->nullable();
                }
                if (! Schema::hasColumn('equipments', 'min_capacity')) {
                    $t->decimal('min_capacity', 12, 2)->nullable();
                }
                if (! Schema::hasColumn('equipments', 'max_capacity')) {
                    $t->decimal('max_capacity', 12, 2)->nullable();
                }
                if (! Schema::hasColumn('equipments', 'resolution')) {
                    $t->decimal('resolution', 12, 2)->nullable();
                }
                if (! Schema::hasColumn('equipments', 'calibration_interval_months')) {
                    $t->integer('calibration_interval_months')->nullable();
                }
            });
        }

        // ── expenses ──
        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $t) {
                if (! Schema::hasColumn('expenses', 'user_id')) {
                    $t->unsignedBigInteger('user_id')->nullable();
                }
            });
        }

        // ── fund_transfers ──
        if (Schema::hasTable('fund_transfers')) {
            Schema::table('fund_transfers', function (Blueprint $t) {
                if (! Schema::hasColumn('fund_transfers', 'from_account_id')) {
                    $t->unsignedBigInteger('from_account_id')->nullable();
                }
                if (! Schema::hasColumn('fund_transfers', 'to_account_id')) {
                    $t->unsignedBigInteger('to_account_id')->nullable();
                }
            });
        }

        // ── imports ──
        if (Schema::hasTable('imports')) {
            Schema::table('imports', function (Blueprint $t) {
                if (! Schema::hasColumn('imports', 'type')) {
                    $t->string('type', 50)->nullable();
                }
                if (! Schema::hasColumn('imports', 'rows_processed')) {
                    $t->integer('rows_processed')->nullable();
                }
                if (! Schema::hasColumn('imports', 'rows_failed')) {
                    $t->integer('rows_failed')->nullable();
                }
            });
        }

        // ── inmetro_instruments ──
        if (Schema::hasTable('inmetro_instruments')) {
            Schema::table('inmetro_instruments', function (Blueprint $t) {
                if (! Schema::hasColumn('inmetro_instruments', 'tenant_id')) {
                    $t->unsignedBigInteger('tenant_id')->nullable();
                }
                if (! Schema::hasColumn('inmetro_instruments', 'type')) {
                    $t->string('type', 50)->nullable();
                }
            });
        }

        // ── inmetro_locations ──
        if (Schema::hasTable('inmetro_locations')) {
            Schema::table('inmetro_locations', function (Blueprint $t) {
                if (! Schema::hasColumn('inmetro_locations', 'tenant_id')) {
                    $t->unsignedBigInteger('tenant_id')->nullable();
                }
            });
        }

        // ── inmetro_owners ──
        if (Schema::hasTable('inmetro_owners')) {
            Schema::table('inmetro_owners', function (Blueprint $t) {
                if (! Schema::hasColumn('inmetro_owners', 'state')) {
                    $t->string('state', 2)->nullable();
                }
            });
        }

        // ── numbering_sequences ──
        if (Schema::hasTable('numbering_sequences')) {
            Schema::table('numbering_sequences', function (Blueprint $t) {
                if (! Schema::hasColumn('numbering_sequences', 'entity_type')) {
                    $t->string('entity_type', 50)->nullable();
                }
            });
        }

        // ── products ──
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $t) {
                if (! Schema::hasColumn('products', 'sku')) {
                    $t->string('sku', 100)->nullable()->unique();
                }
                if (! Schema::hasColumn('products', 'price')) {
                    $t->decimal('price', 12, 2)->nullable();
                }
                if (! Schema::hasColumn('products', 'cost')) {
                    $t->decimal('cost', 12, 2)->nullable();
                }
                if (! Schema::hasColumn('products', 'type')) {
                    $t->string('type', 30)->nullable();
                }
                if (! Schema::hasColumn('products', 'min_stock')) {
                    $t->decimal('min_stock', 12, 2)->nullable();
                }
            });
        }

        // ── quote_items ──
        if (Schema::hasTable('quote_items')) {
            Schema::table('quote_items', function (Blueprint $t) {
                if (! Schema::hasColumn('quote_items', 'quote_id')) {
                    $t->unsignedBigInteger('quote_id')->nullable();
                }
                if (! Schema::hasColumn('quote_items', 'total')) {
                    $t->decimal('total', 12, 2)->nullable();
                }
            });
        }

        // ── stock_transfers ──
        if (Schema::hasTable('stock_transfers')) {
            Schema::table('stock_transfers', function (Blueprint $t) {
                if (! Schema::hasColumn('stock_transfers', 'product_id')) {
                    $t->unsignedBigInteger('product_id')->nullable();
                }
                if (! Schema::hasColumn('stock_transfers', 'quantity')) {
                    $t->decimal('quantity', 12, 2)->nullable();
                }
            });
        }

        // ── tenant_settings ──
        if (Schema::hasTable('tenant_settings')) {
            Schema::table('tenant_settings', function (Blueprint $t) {
                if (! Schema::hasColumn('tenant_settings', 'value')) {
                    $t->text('value')->nullable();
                }
            });
        }

        // ── tenants ──
        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $t) {
                if (! Schema::hasColumn('tenants', 'slug')) {
                    $t->string('slug')->nullable();
                }
                if (! Schema::hasColumn('tenants', 'is_active')) {
                    $t->boolean('is_active')->nullable();
                }
            });
        }

        // ── warehouses ──
        if (Schema::hasTable('warehouses')) {
            Schema::table('warehouses', function (Blueprint $t) {
                if (! Schema::hasColumn('warehouses', 'is_main')) {
                    $t->boolean('is_main')->nullable();
                }
            });
        }

        // ── bank_accounts ──
        if (Schema::hasTable('bank_accounts')) {
            Schema::table('bank_accounts', function (Blueprint $t) {
                if (! Schema::hasColumn('bank_accounts', 'initial_balance')) {
                    $t->decimal('initial_balance', 12, 2)->nullable();
                }
            });
        }

        // ── central_items ──
        if (Schema::hasTable('central_items')) {
            Schema::table('central_items', function (Blueprint $t) {
                if (! Schema::hasColumn('central_items', 'user_id')) {
                    $t->unsignedBigInteger('user_id')->nullable();
                }
                if (! Schema::hasColumn('central_items', 'completed')) {
                    $t->boolean('completed')->nullable();
                }
            });
        }

        // ── Add deleted_at for models that already have SoftDeletes ──
        $softDeletesTables = [
            'inmetro_competitors', 'commission_rules', 'users', 'inmetro_owners',
            'crm_pipelines', 'tenants',
        ];
        foreach ($softDeletesTables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->softDeletes();
                });
            }
        }

        // ── Create tables for missing models ──

        if (! Schema::hasTable('customer_addresses')) {
            Schema::create('customer_addresses', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('customer_id');
                $t->string('type', 30)->nullable();
                $t->string('street')->nullable();
                $t->string('number', 20)->nullable();
                $t->string('complement')->nullable();
                $t->string('district')->nullable();
                $t->string('city')->nullable();
                $t->string('state', 2)->nullable();
                $t->string('zip', 10)->nullable();
                $t->string('country', 5)->default('BR');
                $t->boolean('is_main')->default(false);
                $t->decimal('latitude', 10, 8)->nullable();
                $t->decimal('longitude', 11, 8)->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'customer_id'], 'cust_addr_tenant_cust_idx');
            });
        }

        if (! Schema::hasTable('crm_deal_products')) {
            Schema::create('crm_deal_products', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('deal_id');
                $t->unsignedBigInteger('product_id');
                $t->decimal('quantity', 12, 2)->default(1);
                $t->decimal('unit_price', 12, 2)->default(0);
                $t->decimal('total', 12, 2)->default(0);
                $t->timestamps();
                $t->index(['tenant_id', 'deal_id'], 'crm_dp_tenant_deal_idx');
            });
        }

        if (! Schema::hasTable('crm_deal_stage_histories')) {
            Schema::create('crm_deal_stage_histories', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('deal_id');
                $t->unsignedBigInteger('from_stage_id')->nullable();
                $t->unsignedBigInteger('to_stage_id');
                $t->unsignedBigInteger('changed_by')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'deal_id'], 'crm_dsh_tenant_deal_idx');
            });
        }

        if (! Schema::hasTable('crm_follow_up_tasks')) {
            Schema::create('crm_follow_up_tasks', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('deal_id');
                $t->unsignedBigInteger('user_id');
                $t->string('title');
                $t->text('description')->nullable();
                $t->timestamp('due_at')->nullable();
                $t->string('status', 20)->default('pending');
                $t->timestamp('completed_at')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'deal_id'], 'crm_fut_tenant_deal_idx');
            });
        }

        if (! Schema::hasTable('fleets')) {
            Schema::create('fleets', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->string('plate', 20)->nullable();
                $t->string('brand', 100)->nullable();
                $t->string('model', 100)->nullable();
                $t->string('year', 10)->nullable();
                $t->string('color', 50)->nullable();
                $t->string('type', 30)->nullable();
                $t->string('status', 20)->default('active');
                $t->decimal('mileage', 12, 2)->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->softDeletes();
                $t->index('tenant_id');
            });
        }

        if (! Schema::hasTable('surveys')) {
            Schema::create('surveys', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->string('title');
                $t->text('description')->nullable();
                $t->string('status', 20)->default('draft');
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamp('starts_at')->nullable();
                $t->timestamp('ends_at')->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->softDeletes();
                $t->index('tenant_id');
            });
        }

        if (! Schema::hasTable('fiscal_invoices')) {
            Schema::create('fiscal_invoices', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->string('number')->nullable();
                $t->string('series', 10)->nullable();
                $t->string('type', 20)->nullable();
                $t->unsignedBigInteger('customer_id')->nullable();
                $t->unsignedBigInteger('work_order_id')->nullable();
                $t->decimal('total', 12, 2)->default(0);
                $t->string('status', 20)->default('pending');
                $t->timestamp('issued_at')->nullable();
                $t->longText('xml')->nullable();
                $t->string('pdf_url')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->unique(['tenant_id', 'number'], 'finv_tenant_number_uniq');
            });
        }

        if (! Schema::hasTable('email_logs')) {
            Schema::create('email_logs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->string('to');
                $t->string('subject');
                $t->text('body')->nullable();
                $t->string('status', 20)->default('sent');
                $t->timestamp('sent_at')->nullable();
                $t->text('error')->nullable();
                $t->string('related_type')->nullable();
                $t->unsignedBigInteger('related_id')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'status'], 'email_logs_tenant_status_idx');
            });
        }

        if (! Schema::hasTable('inmetro_snapshots')) {
            Schema::create('inmetro_snapshots', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('competitor_id');
                $t->json('data')->nullable();
                $t->timestamp('captured_at')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'competitor_id'], 'imsnap_tenant_comp_idx');
            });
        }

        if (! Schema::hasTable('account_payable_installments')) {
            Schema::create('account_payable_installments', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('account_payable_id');
                $t->integer('installment_number')->default(1);
                $t->date('due_date');
                $t->decimal('amount', 12, 2);
                $t->decimal('paid_amount', 12, 2)->default(0);
                $t->string('status', 20)->default('pending');
                $t->timestamp('paid_at')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'account_payable_id'], 'ap_inst_tenant_ap_idx');
            });
        }

        if (! Schema::hasTable('account_payable_payments')) {
            Schema::create('account_payable_payments', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('account_payable_id');
                $t->unsignedBigInteger('installment_id')->nullable();
                $t->decimal('amount', 12, 2);
                $t->date('payment_date');
                $t->string('payment_method', 50)->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'account_payable_id'], 'ap_pay_tenant_ap_idx');
            });
        }

        if (! Schema::hasTable('account_receivable_installments')) {
            Schema::create('account_receivable_installments', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('account_receivable_id');
                $t->integer('installment_number')->default(1);
                $t->date('due_date');
                $t->decimal('amount', 12, 2);
                $t->decimal('paid_amount', 12, 2)->default(0);
                $t->string('status', 20)->default('pending');
                $t->timestamp('paid_at')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'account_receivable_id'], 'ar_inst_tenant_ar_idx');
            });
        }

        if (! Schema::hasTable('fiscal_invoice_items')) {
            Schema::create('fiscal_invoice_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('fiscal_invoice_id');
                $t->string('description')->nullable();
                $t->decimal('quantity', 12, 2)->default(1);
                $t->decimal('unit_price', 12, 2)->default(0);
                $t->decimal('total', 12, 2)->default(0);
                $t->unsignedBigInteger('product_id')->nullable();
                $t->unsignedBigInteger('service_id')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'fiscal_invoice_id'], 'fii_tenant_invoice_idx');
            });
        }

        if (! Schema::hasTable('export_jobs')) {
            Schema::create('export_jobs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('type', 50)->nullable();
                $t->string('status', 20)->default('pending');
                $t->string('file_path')->nullable();
                $t->json('filters')->nullable();
                $t->timestamp('started_at')->nullable();
                $t->timestamp('completed_at')->nullable();
                $t->text('error')->nullable();
                $t->timestamps();
                $t->index('tenant_id');
            });
        }

        if (! Schema::hasTable('fleet_fuel_entries')) {
            Schema::create('fleet_fuel_entries', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('fleet_id');
                $t->date('date')->nullable();
                $t->string('fuel_type', 30)->nullable();
                $t->decimal('liters', 10, 2)->nullable();
                $t->decimal('cost', 12, 2)->nullable();
                $t->integer('odometer')->nullable();
                $t->string('station')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'fleet_id'], 'fleet_fuel_tenant_idx');
            });
        }

        if (! Schema::hasTable('fleet_trips')) {
            Schema::create('fleet_trips', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('fleet_id');
                $t->unsignedBigInteger('driver_user_id')->nullable();
                $t->date('date')->nullable();
                $t->string('origin')->nullable();
                $t->string('destination')->nullable();
                $t->decimal('distance_km', 10, 2)->nullable();
                $t->string('purpose')->nullable();
                $t->integer('odometer_start')->nullable();
                $t->integer('odometer_end')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'fleet_id'], 'fleet_trip_tenant_idx');
            });
        }

        if (! Schema::hasTable('survey_responses')) {
            Schema::create('survey_responses', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('survey_id');
                $t->unsignedBigInteger('respondent_id')->nullable();
                $t->json('answers')->nullable();
                $t->decimal('score', 5, 2)->nullable();
                $t->timestamp('completed_at')->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'survey_id'], 'survey_resp_tenant_idx');
            });
        }

        if (! Schema::hasTable('fleet_maintenances')) {
            Schema::create('fleet_maintenances', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('fleet_id');
                $t->string('type', 50)->nullable();
                $t->text('description')->nullable();
                $t->date('date')->nullable();
                $t->decimal('cost', 12, 2)->nullable();
                $t->integer('odometer')->nullable();
                $t->date('next_date')->nullable();
                $t->string('status', 30)->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
                $t->index(['tenant_id', 'fleet_id'], 'fleet_maint_tenant_idx');
            });
        }

        if (! Schema::hasTable('portal_ticket_comments')) {
            Schema::create('portal_ticket_comments', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('tenant_id');
                $t->unsignedBigInteger('portal_ticket_id');
                $t->unsignedBigInteger('user_id')->nullable();
                $t->text('content');
                $t->boolean('is_internal')->default(false);
                $t->timestamps();
                $t->index(['tenant_id', 'portal_ticket_id'], 'ptc_tenant_ticket_idx');
            });
        }
    }

    public function down(): void
    {
        // Each column drop is guarded to prevent errors
        $drops = [
            'commission_rules' => ['percentage', 'fixed_amount'],
            'commission_events' => ['amount', 'proportion'],
            'commission_settlements' => ['total', 'closed_by', 'closed_at', 'approved_by', 'approved_at'],
            'commission_goals' => ['target_value', 'current_value', 'type'],
            'commission_campaigns' => ['is_active'],
            'recurring_commissions' => ['frequency'],
            'crm_pipeline_stages' => ['order'],
            'customers' => ['company_name'],
            'equipments' => ['accuracy_class', 'min_capacity', 'max_capacity', 'resolution', 'calibration_interval_months'],
            'expenses' => ['user_id'],
            'fund_transfers' => ['from_account_id', 'to_account_id'],
            'imports' => ['type', 'rows_processed', 'rows_failed'],
            'inmetro_instruments' => ['tenant_id', 'type'],
            'inmetro_locations' => ['tenant_id'],
            'inmetro_owners' => ['state'],
            'numbering_sequences' => ['entity_type'],
            'products' => ['sku', 'price', 'cost', 'type', 'min_stock'],
            'quote_items' => ['quote_id', 'total'],
            'stock_transfers' => ['product_id', 'quantity'],
            'tenant_settings' => ['value'],
            'tenants' => ['slug', 'is_active'],
            'warehouses' => ['is_main'],
            'bank_accounts' => ['initial_balance'],
            'central_items' => ['user_id', 'completed'],
        ];

        foreach ($drops as $table => $columns) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) use ($table, $columns) {
                    foreach ($columns as $col) {
                        if (Schema::hasColumn($table, $col)) {
                            $t->dropColumn($col);
                        }
                    }
                });
            }
        }
    }
};
