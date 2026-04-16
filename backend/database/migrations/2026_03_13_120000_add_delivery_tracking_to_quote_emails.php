<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quote_emails')) {
            return;
        }

        Schema::table('quote_emails', function (Blueprint $table) {
            if (! Schema::hasColumn('quote_emails', 'queued_at')) {
                $table->timestamp('queued_at')->nullable();
            }

            if (! Schema::hasColumn('quote_emails', 'sent_at')) {
                $table->timestamp('sent_at')->nullable();
            }

            if (! Schema::hasColumn('quote_emails', 'failed_at')) {
                $table->timestamp('failed_at')->nullable();
            }

            if (! Schema::hasColumn('quote_emails', 'error_message')) {
                $table->text('error_message')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('quote_emails')) {
            return;
        }

        Schema::table('quote_emails', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('quote_emails', 'queued_at') ? 'queued_at' : null,
                Schema::hasColumn('quote_emails', 'sent_at') ? 'sent_at' : null,
                Schema::hasColumn('quote_emails', 'failed_at') ? 'failed_at' : null,
                Schema::hasColumn('quote_emails', 'error_message') ? 'error_message' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
