<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('equipment_calibrations') || ! Schema::hasColumn('equipment_calibrations', 'result')) {
            return;
        }

        $map = [
            'aprovado' => 'approved',
            'aprovado_com_ressalva' => 'approved_with_restriction',
            'reprovado' => 'rejected',
        ];

        foreach ($map as $old => $new) {
            DB::table('equipment_calibrations')->where('result', $old)->update(['result' => $new]);
        }

        Schema::table('equipment_calibrations', function (Blueprint $table) {
            $table->string('result', 30)->default('approved')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('equipment_calibrations') || ! Schema::hasColumn('equipment_calibrations', 'result')) {
            return;
        }

        $map = [
            'approved' => 'aprovado',
            'approved_with_restriction' => 'aprovado_com_ressalva',
            'rejected' => 'reprovado',
        ];

        foreach ($map as $old => $new) {
            DB::table('equipment_calibrations')->where('result', $old)->update(['result' => $new]);
        }

        Schema::table('equipment_calibrations', function (Blueprint $table) {
            $table->string('result', 30)->default('aprovado')->change();
        });
    }
};
