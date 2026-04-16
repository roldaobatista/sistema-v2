<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Equipment status: Portuguese → English lowercase
        $equipmentMap = [
            'ativo' => 'active',
            'em_calibracao' => 'in_calibration',
            'em_manutencao' => 'in_maintenance',
            'fora_de_uso' => 'out_of_service',
            'descartado' => 'discarded',
        ];

        foreach ($equipmentMap as $old => $new) {
            DB::table('equipments')->where('status', $old)->update(['status' => $new]);
        }

        // Also update default value if any column default exists
        // (handled by model constants, no schema change needed)
    }

    public function down(): void
    {
        $equipmentMap = [
            'active' => 'ativo',
            'in_calibration' => 'em_calibracao',
            'in_maintenance' => 'em_manutencao',
            'out_of_service' => 'fora_de_uso',
            'discarded' => 'descartado',
        ];

        foreach ($equipmentMap as $old => $new) {
            DB::table('equipments')->where('status', $old)->update(['status' => $new]);
        }
    }
};
