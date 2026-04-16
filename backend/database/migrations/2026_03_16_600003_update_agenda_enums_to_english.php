<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'central_items';

        // Status: UPPERCASE Portuguese → lowercase English
        $statusMap = [
            'ABERTO' => 'open',
            'EM_ANDAMENTO' => 'in_progress',
            'AGUARDANDO' => 'waiting',
            'CONCLUIDO' => 'completed',
            'CANCELADO' => 'cancelled',
        ];
        foreach ($statusMap as $old => $new) {
            DB::table($table)->where('status', $old)->update(['status' => $new]);
        }

        // Priority: UPPERCASE Portuguese → lowercase English
        $priorityMap = [
            'BAIXA' => 'low',
            'MEDIA' => 'medium',
            'ALTA' => 'high',
            'URGENTE' => 'urgent',
        ];
        foreach ($priorityMap as $old => $new) {
            DB::table($table)->where('prioridade', $old)->update(['prioridade' => $new]);
        }

        // Type: UPPERCASE Portuguese → lowercase English
        $typeMap = [
            'CHAMADO' => 'service_call',
            'OS' => 'work_order',
            'FINANCEIRO' => 'financial',
            'ORCAMENTO' => 'quote',
            'TAREFA' => 'task',
            'LEMBRETE' => 'reminder',
            'CALIBRACAO' => 'calibration',
            'CONTRATO' => 'contract',
            'OUTRO' => 'other',
        ];
        foreach ($typeMap as $old => $new) {
            DB::table($table)->where('tipo', $old)->update(['tipo' => $new]);
        }

        // Visibility: UPPERCASE Portuguese → lowercase English
        $visibilityMap = [
            'PRIVADO' => 'private',
            'EQUIPE' => 'team',
            'DEPARTAMENTO' => 'department',
            'CUSTOM' => 'custom',
            'EMPRESA' => 'company',
        ];
        foreach ($visibilityMap as $old => $new) {
            DB::table($table)->where('visibilidade', $old)->update(['visibilidade' => $new]);
        }

        // Origin: UPPERCASE → lowercase English
        $originMap = [
            'MANUAL' => 'manual',
            'AUTO' => 'auto',
            'JOB' => 'job',
        ];
        foreach ($originMap as $old => $new) {
            DB::table($table)->where('origem', $old)->update(['origem' => $new]);
        }

        // Also update central_templates
        if (DB::getDriverName() !== 'sqlite') {
            // Templates store tipo/prioridade/visibilidade as plain strings
            foreach ($typeMap as $old => $new) {
                DB::table('central_templates')->where('tipo', $old)->update(['tipo' => $new]);
            }
            foreach ($priorityMap as $old => $new) {
                DB::table('central_templates')->where('prioridade', $old)->update(['prioridade' => $new]);
            }
            foreach ($visibilityMap as $old => $new) {
                DB::table('central_templates')->where('visibilidade', $old)->update(['visibilidade' => $new]);
            }
        }

        // Also update central_rules
        if (DB::getDriverName() !== 'sqlite') {
            foreach ($typeMap as $old => $new) {
                DB::table('central_rules')->where('tipo_item', $old)->update(['tipo_item' => $new]);
            }
            foreach ($priorityMap as $old => $new) {
                DB::table('central_rules')->where('prioridade_minima', $old)->update(['prioridade_minima' => $new]);
            }
        }
    }

    public function down(): void
    {
        $table = 'central_items';

        $statusMap = [
            'open' => 'ABERTO',
            'in_progress' => 'EM_ANDAMENTO',
            'waiting' => 'AGUARDANDO',
            'completed' => 'CONCLUIDO',
            'cancelled' => 'CANCELADO',
        ];
        foreach ($statusMap as $old => $new) {
            DB::table($table)->where('status', $old)->update(['status' => $new]);
        }

        $priorityMap = [
            'low' => 'BAIXA',
            'medium' => 'MEDIA',
            'high' => 'ALTA',
            'urgent' => 'URGENTE',
        ];
        foreach ($priorityMap as $old => $new) {
            DB::table($table)->where('prioridade', $old)->update(['prioridade' => $new]);
        }

        $typeMap = [
            'service_call' => 'CHAMADO',
            'work_order' => 'OS',
            'financial' => 'FINANCEIRO',
            'quote' => 'ORCAMENTO',
            'task' => 'TAREFA',
            'reminder' => 'LEMBRETE',
            'calibration' => 'CALIBRACAO',
            'contract' => 'CONTRATO',
            'other' => 'OUTRO',
        ];
        foreach ($typeMap as $old => $new) {
            DB::table($table)->where('tipo', $old)->update(['tipo' => $new]);
        }

        $visibilityMap = [
            'private' => 'PRIVADO',
            'team' => 'EQUIPE',
            'department' => 'DEPARTAMENTO',
            'custom' => 'CUSTOM',
            'company' => 'EMPRESA',
        ];
        foreach ($visibilityMap as $old => $new) {
            DB::table($table)->where('visibilidade', $old)->update(['visibilidade' => $new]);
        }

        $originMap = [
            'manual' => 'MANUAL',
            'auto' => 'AUTO',
            'job' => 'JOB',
        ];
        foreach ($originMap as $old => $new) {
            DB::table($table)->where('origem', $old)->update(['origem' => $new]);
        }
    }
};
