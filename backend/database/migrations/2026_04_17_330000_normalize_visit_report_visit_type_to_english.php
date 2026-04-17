<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('visit_reports') || ! Schema::hasColumn('visit_reports', 'visit_type')) {
            return;
        }

        DB::table('visit_reports')->where('visit_type', 'presencial')->update(['visit_type' => 'in_person']);

        Schema::table('visit_reports', function (Blueprint $table) {
            $table->string('visit_type', 255)->default('in_person')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('visit_reports') || ! Schema::hasColumn('visit_reports', 'visit_type')) {
            return;
        }

        DB::table('visit_reports')->where('visit_type', 'in_person')->update(['visit_type' => 'presencial']);

        Schema::table('visit_reports', function (Blueprint $table) {
            $table->string('visit_type', 255)->default('presencial')->change();
        });
    }
};
