<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('equipments') || ! Schema::hasColumn('equipments', 'status')) {
            return;
        }

        $map = [
            'ativo' => 'active',
            'Ativo' => 'active',
            'em_calibracao' => 'in_calibration',
            'Em Calibração' => 'in_calibration',
            'em_manutencao' => 'in_maintenance',
            'Em Manutenção' => 'in_maintenance',
            'fora_de_uso' => 'out_of_service',
            'Fora de Uso' => 'out_of_service',
            'descartado' => 'discarded',
            'Descartado' => 'discarded',
        ];

        foreach ($map as $legacy => $normalized) {
            DB::table('equipments')->where('status', $legacy)->update(['status' => $normalized]);
        }
    }

    public function down(): void
    {
        // Migration corretiva forward-only:
        // reverter para valores legados em portugues reintroduziria inconsistencia.
    }
};
