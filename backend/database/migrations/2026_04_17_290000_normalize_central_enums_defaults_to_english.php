<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('central_items')) {
            $statusMap = ['ABERTO' => 'open', 'EM_ANDAMENTO' => 'in_progress', 'CONCLUIDO' => 'completed', 'CANCELADO' => 'cancelled'];
            foreach ($statusMap as $old => $new) {
                DB::table('central_items')->where('status', $old)->update(['status' => $new]);
            }
            $prioMap = ['BAIXA' => 'low', 'MEDIA' => 'medium', 'ALTA' => 'high', 'URGENTE' => 'urgent'];
            foreach ($prioMap as $old => $new) {
                DB::table('central_items')->where('prioridade', $old)->update(['prioridade' => $new]);
            }
            $visMap = ['PRIVADO' => 'private', 'EQUIPE' => 'team', 'PUBLICO' => 'public'];
            foreach ($visMap as $old => $new) {
                DB::table('central_items')->where('visibilidade', $old)->update(['visibilidade' => $new]);
            }
            $origemMap = ['MANUAL' => 'manual', 'AUTOMATICO' => 'automatic'];
            foreach ($origemMap as $old => $new) {
                DB::table('central_items')->where('origem', $old)->update(['origem' => $new]);
            }

            Schema::table('central_items', function (Blueprint $table) {
                $table->string('status', 20)->default('open')->change();
                $table->string('prioridade', 20)->default('medium')->change();
                $table->string('visibilidade', 20)->default('team')->change();
                $table->string('origem', 20)->default('manual')->change();
            });
        }

        if (Schema::hasTable('central_templates')) {
            $prioMap = ['BAIXA' => 'low', 'MEDIA' => 'medium', 'ALTA' => 'high', 'URGENTE' => 'urgent'];
            foreach ($prioMap as $old => $new) {
                DB::table('central_templates')->where('prioridade', $old)->update(['prioridade' => $new]);
            }
            $visMap = ['PRIVADO' => 'private', 'EQUIPE' => 'team', 'PUBLICO' => 'public'];
            foreach ($visMap as $old => $new) {
                DB::table('central_templates')->where('visibilidade', $old)->update(['visibilidade' => $new]);
            }

            Schema::table('central_templates', function (Blueprint $table) {
                $table->string('prioridade', 20)->default('medium')->change();
                $table->string('visibilidade', 20)->default('team')->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('central_items')) {
            Schema::table('central_items', function (Blueprint $table) {
                $table->string('status', 20)->default('ABERTO')->change();
                $table->string('prioridade', 20)->default('MEDIA')->change();
                $table->string('visibilidade', 20)->default('EQUIPE')->change();
                $table->string('origem', 20)->default('MANUAL')->change();
            });
        }

        if (Schema::hasTable('central_templates')) {
            Schema::table('central_templates', function (Blueprint $table) {
                $table->string('prioridade', 20)->default('MEDIA')->change();
                $table->string('visibilidade', 20)->default('EQUIPE')->change();
            });
        }
    }
};
