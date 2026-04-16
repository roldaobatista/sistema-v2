<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Infra audit: indexes restantes em tabelas de alta frequência
 * que não foram cobertos pelas migrations anteriores (2026_03_15/16).
 *
 * Foco: FK sem index, tenant_id sem index, status sem index,
 * tabelas de auditoria/notificação/email/central.
 */
return new class extends Migration
{
    public function up(): void
    {
        // notifications — user_id é FK e coluna mais filtrada
        if (Schema::hasTable('notifications') && Schema::hasColumn('notifications', 'user_id')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index(['user_id', 'read_at'], 'notif_user_read');
                if (Schema::hasColumn('notifications', 'tenant_id')) {
                    $table->index(['tenant_id', 'created_at'], 'notif_tenant_created');
                }
            });
        }

        // audit_logs — polymorphic (auditable_type, auditable_id) para rastreamento
        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                if (Schema::hasColumn('audit_logs', 'auditable_type') && Schema::hasColumn('audit_logs', 'auditable_id')) {
                    $table->index(['auditable_type', 'auditable_id'], 'audit_auditable_poly');
                }
                if (Schema::hasColumn('audit_logs', 'user_id')) {
                    $table->index('user_id', 'audit_user');
                }
            });
        }

        // central_items — status e prioridade para dashboards
        if (Schema::hasTable('central_items')) {
            Schema::table('central_items', function (Blueprint $table) {
                if (Schema::hasColumn('central_items', 'status')) {
                    $table->index(['tenant_id', 'status'], 'ci_tenant_status');
                }
                if (Schema::hasColumn('central_items', 'assigned_to')) {
                    $table->index(['tenant_id', 'assigned_to'], 'ci_tenant_assigned');
                }
                if (Schema::hasColumn('central_items', 'due_date')) {
                    $table->index(['tenant_id', 'due_date'], 'ci_tenant_due');
                }
            });
        }

        // central_item_comments — FK user_id
        if (Schema::hasTable('central_item_comments') && Schema::hasColumn('central_item_comments', 'user_id')) {
            Schema::table('central_item_comments', function (Blueprint $table) {
                $table->index('user_id', 'cic_user');
            });
        }

        // central_item_watchers — FK user_id
        if (Schema::hasTable('central_item_watchers') && Schema::hasColumn('central_item_watchers', 'user_id')) {
            Schema::table('central_item_watchers', function (Blueprint $table) {
                $table->index('user_id', 'ciw_user');
            });
        }

        // central_subtasks — FK parent
        if (Schema::hasTable('central_subtasks') && Schema::hasColumn('central_subtasks', 'central_item_id')) {
            Schema::table('central_subtasks', function (Blueprint $table) {
                $table->index('central_item_id', 'csub_item');
            });
        }

        // emails — tenant + folder para listagem
        if (Schema::hasTable('emails')) {
            Schema::table('emails', function (Blueprint $table) {
                if (Schema::hasColumn('emails', 'tenant_id') && Schema::hasColumn('emails', 'folder')) {
                    if (Schema::hasColumn('emails', 'received_at')) {
                        $table->index(['tenant_id', 'folder', 'received_at'], 'em_tenant_folder_received');
                    } elseif (Schema::hasColumn('emails', 'date')) {
                        $table->index(['tenant_id', 'folder', 'date'], 'em_tenant_folder_date');
                    }
                }
                if (Schema::hasColumn('emails', 'email_account_id')) {
                    $table->index('email_account_id', 'em_account');
                }
            });
        }

        // email_attachments — FK email_id
        if (Schema::hasTable('email_attachments') && Schema::hasColumn('email_attachments', 'email_id')) {
            Schema::table('email_attachments', function (Blueprint $table) {
                $table->index('email_id', 'ea_email');
            });
        }

        // fiscal_notes — tenant + status para dashboard fiscal
        if (Schema::hasTable('fiscal_notes')) {
            Schema::table('fiscal_notes', function (Blueprint $table) {
                if (Schema::hasColumn('fiscal_notes', 'tenant_id') && Schema::hasColumn('fiscal_notes', 'status')) {
                    $table->index(['tenant_id', 'status'], 'fn_tenant_status');
                }
                if (Schema::hasColumn('fiscal_notes', 'customer_id')) {
                    $table->index('customer_id', 'fn_customer');
                }
            });
        }

        // service_call_equipments — FK service_call_id
        if (Schema::hasTable('service_call_equipments') && Schema::hasColumn('service_call_equipments', 'service_call_id')) {
            Schema::table('service_call_equipments', function (Blueprint $table) {
                $table->index('service_call_id', 'sce_service_call');
            });
        }

        // work_order_attachments — FK work_order_id
        if (Schema::hasTable('work_order_attachments') && Schema::hasColumn('work_order_attachments', 'work_order_id')) {
            Schema::table('work_order_attachments', function (Blueprint $table) {
                $table->index('work_order_id', 'woa_work_order');
            });
        }

        // work_order_chats — FK work_order_id
        if (Schema::hasTable('work_order_chats') && Schema::hasColumn('work_order_chats', 'work_order_id')) {
            Schema::table('work_order_chats', function (Blueprint $table) {
                $table->index('work_order_id', 'woc_work_order');
            });
        }

        // recurring_contracts — tenant + status
        if (Schema::hasTable('recurring_contracts')) {
            Schema::table('recurring_contracts', function (Blueprint $table) {
                if (Schema::hasColumn('recurring_contracts', 'tenant_id') && Schema::hasColumn('recurring_contracts', 'status')) {
                    $table->index(['tenant_id', 'status'], 'rc_tenant_status');
                }
                if (Schema::hasColumn('recurring_contracts', 'customer_id')) {
                    $table->index('customer_id', 'rc_customer');
                }
            });
        }

        // push_subscriptions — FK user_id
        if (Schema::hasTable('push_subscriptions') && Schema::hasColumn('push_subscriptions', 'user_id')) {
            Schema::table('push_subscriptions', function (Blueprint $table) {
                $table->index('user_id', 'ps_user');
            });
        }

        // bank_statement_entries — FK bank_statement_id para reconciliação
        if (Schema::hasTable('bank_statement_entries') && Schema::hasColumn('bank_statement_entries', 'bank_statement_id')) {
            Schema::table('bank_statement_entries', function (Blueprint $table) {
                $table->index('bank_statement_id', 'bse_statement');
            });
        }

        // leave_requests — tenant + user + status
        if (Schema::hasTable('leave_requests')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                if (Schema::hasColumn('leave_requests', 'tenant_id') && Schema::hasColumn('leave_requests', 'user_id')) {
                    $table->index(['tenant_id', 'user_id', 'status'], 'lr_tenant_user_status');
                }
            });
        }

        // trainings — tenant_id
        if (Schema::hasTable('trainings') && Schema::hasColumn('trainings', 'tenant_id')) {
            Schema::table('trainings', function (Blueprint $table) {
                $table->index('tenant_id', 'train_tenant');
            });
        }

        // crm_activities — tenant + deal_id para timeline
        if (Schema::hasTable('crm_activities')) {
            Schema::table('crm_activities', function (Blueprint $table) {
                if (Schema::hasColumn('crm_activities', 'deal_id')) {
                    $table->index('deal_id', 'crma_deal');
                }
                if (Schema::hasColumn('crm_activities', 'contact_id')) {
                    $table->index('contact_id', 'crma_contact');
                }
            });
        }

        // import — tenant + status
        if (Schema::hasTable('imports') && Schema::hasColumn('imports', 'tenant_id')) {
            Schema::table('imports', function (Blueprint $table) {
                if (Schema::hasColumn('imports', 'status')) {
                    $table->index(['tenant_id', 'status'], 'imp_tenant_status');
                }
            });
        }
    }

    public function down(): void
    {
        $drops = [
            'notifications' => ['notif_user_read', 'notif_tenant_created'],
            'audit_logs' => ['audit_auditable_poly', 'audit_user'],
            'central_items' => ['ci_tenant_status', 'ci_tenant_assigned', 'ci_tenant_due'],
            'central_item_comments' => ['cic_user'],
            'central_item_watchers' => ['ciw_user'],
            'central_subtasks' => ['csub_item'],
            'emails' => ['em_tenant_folder_received', 'em_tenant_folder_date', 'em_account'],
            'email_attachments' => ['ea_email'],
            'fiscal_notes' => ['fn_tenant_status', 'fn_customer'],
            'service_call_equipments' => ['sce_service_call'],
            'work_order_attachments' => ['woa_work_order'],
            'work_order_chats' => ['woc_work_order'],
            'recurring_contracts' => ['rc_tenant_status', 'rc_customer'],
            'push_subscriptions' => ['ps_user'],
            'bank_statement_entries' => ['bse_statement'],
            'leave_requests' => ['lr_tenant_user_status'],
            'trainings' => ['train_tenant'],
            'crm_activities' => ['crma_deal', 'crma_contact'],
            'imports' => ['imp_tenant_status'],
        ];

        foreach ($drops as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($indexes) {
                foreach ($indexes as $indexName) {
                    try {
                        $t->dropIndex($indexName);
                    } catch (Throwable) {
                    }
                }
            });
        }
    }
};
