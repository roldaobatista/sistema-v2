<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_web_forms')) {
            return;
        }

        Schema::table('crm_web_forms', function (Blueprint $table) {
            // Drop the slug-only unique index that prevents multi-tenant seeding
            // Using Laravel 12 native API (Doctrine DBAL was removed)
            $indexes = Schema::getIndexes('crm_web_forms');

            foreach ($indexes as $index) {
                if ($index['columns'] === ['slug'] && $index['unique']) {
                    $table->dropIndex($index['name']);
                    break;
                }
            }

            // Create proper multi-tenant unique index
            if (! collect(Schema::getIndexes('crm_web_forms'))->contains('name', 'crm_web_forms_tenant_slug_unique')) {
                $table->unique(['tenant_id', 'slug'], 'crm_web_forms_tenant_slug_unique');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_web_forms')) {
            return;
        }

        Schema::table('crm_web_forms', function (Blueprint $table) {
            $table->dropUnique('crm_web_forms_tenant_slug_unique');
            $table->unique('slug');
        });
    }
};
