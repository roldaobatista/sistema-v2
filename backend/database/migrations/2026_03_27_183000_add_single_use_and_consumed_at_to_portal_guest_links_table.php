<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portal_guest_links')) {
            return;
        }

        Schema::table('portal_guest_links', function (Blueprint $table) {
            if (! Schema::hasColumn('portal_guest_links', 'single_use')) {
                $table->boolean('single_use')->default(true);
            }

            if (! Schema::hasColumn('portal_guest_links', 'consumed_at')) {
                $table->timestamp('consumed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('portal_guest_links')) {
            return;
        }

        Schema::table('portal_guest_links', function (Blueprint $table) {
            if (Schema::hasColumn('portal_guest_links', 'single_use')) {
                $table->dropColumn('single_use');
            }

            if (Schema::hasColumn('portal_guest_links', 'consumed_at')) {
                $table->dropColumn('consumed_at');
            }
        });
    }
};
