<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('standard_weights')) {
            return;
        }

        if (! Schema::hasColumn('standard_weights', 'shape')) {
            return;
        }

        $map = [
            'cilindrico' => 'cylindrical',
            'retangular' => 'rectangular',
            'disco' => 'disc',
            'paralelepipedo' => 'parallelepiped',
            'outro' => 'other',
        ];

        foreach ($map as $old => $new) {
            DB::table('standard_weights')->where('shape', $old)->update(['shape' => $new]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('standard_weights') || ! Schema::hasColumn('standard_weights', 'shape')) {
            return;
        }

        $map = [
            'cylindrical' => 'cilindrico',
            'rectangular' => 'retangular',
            'disc' => 'disco',
            'parallelepiped' => 'paralelepipedo',
            'other' => 'outro',
        ];

        foreach ($map as $old => $new) {
            DB::table('standard_weights')->where('shape', $old)->update(['shape' => $new]);
        }
    }
};
