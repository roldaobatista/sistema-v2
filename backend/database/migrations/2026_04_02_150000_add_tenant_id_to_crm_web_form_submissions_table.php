<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('crm_web_form_submissions', 'tenant_id')) {
            Schema::table('crm_web_form_submissions', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            });
        }

        // Backfill tenant_id from parent form
        DB::statement('
            UPDATE crm_web_form_submissions
            SET tenant_id = (
                SELECT crm_web_forms.tenant_id
                FROM crm_web_forms
                WHERE crm_web_forms.id = crm_web_form_submissions.form_id
            )
            WHERE tenant_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('crm_web_form_submissions', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
