<?php

namespace App\Actions\ServiceCall;

use App\Models\ServiceCall;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Carbon;

class ExportCsvServiceCallAction extends BaseServiceCallAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $query = ServiceCall::with(['customer:id,name', 'technician:id,name'])
            ->where('tenant_id', $tenantId);

        if ($status = ($data['status'] ?? null)) {
            $query->where('status', $status);
        }
        if ($priority = ($data['priority'] ?? null)) {
            $query->where('priority', $priority);
        }
        if ($techId = ($data['technician_id'] ?? null)) {
            $query->where('technician_id', $techId);
        }
        if ($dateFrom = ($data['date_from'] ?? null)) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo = ($data['date_to'] ?? null)) {
            $query->where('created_at', '<=', $dateTo.' 23:59:59');
        }

        $calls = $query->orderByDesc('created_at')->get();

        $rows = [['Nº', 'Cliente', 'Técnico', 'Status', 'Prioridade', 'Cidade', 'UF', 'Agendado', 'Criado', 'SLA Estourado']];
        foreach ($calls as $call) {
            $rows[] = [
                $call->call_number,
                $call->customer->name ?? '',
                $call->technician->name ?? '',
                $call->status->label(),
                ServiceCall::PRIORITIES[$call->priority]['label'] ?? $call->priority,
                $call->city ?? '',
                $call->state ?? '',
                $call->scheduled_date ? Carbon::parse($call->scheduled_date)->format('d/m/Y H:i') : '',
                $call->created_at->format('d/m/Y H:i'),
                $call->sla_breached ? 'Sim' : 'Não',
            ];
        }

        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(';', array_map(fn ($v) => '"'.str_replace('"', '""', $v).'"', $row))."\n";
        }

        return ApiResponse::data(['csv' => $csv, 'filename' => 'chamados_'.now()->format('Y-m-d').'.csv']);
    }
}
