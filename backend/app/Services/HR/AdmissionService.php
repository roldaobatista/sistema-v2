<?php

namespace App\Services\HR;

use App\Models\Admission;
use App\Models\SystemAlert;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdmissionService
{
    /**
     * Tenta avancar o estado de admissao baseado na Maquina de Estados.
     * Retorna a Admission se sucesso, ou dispara uma Exception (Guard error).
     */
    public function advance(Admission $admission): Admission
    {
        return DB::transaction(function () use ($admission) {
            // Lock para manter consistencia no DB
            $lock = $admission->lockForUpdate()->first();

            if (! $lock) {
                throw new Exception('Admissão concorrida.');
            }

            switch ($admission->status) {
                case 'candidate_approved':
                    if (! $admission->salary_confirmed) {
                        throw new Exception('Vaga ou salário não definidos.');
                    }
                    $admission->status = 'document_collection';
                    break;

                case 'document_collection':
                    if (! $admission->documents_completed) {
                        throw new Exception('Documentos obrigatórios pendentes (CPF, RG, CTPS, Banco, Endereço).');
                    }
                    $admission->status = 'medical_exam';
                    break;

                case 'medical_exam':
                    if ($admission->aso_result === 'failed') {
                        $admission->status = 'document_collection';
                        break;
                    }

                    if ($admission->aso_result !== 'approved') {
                        throw new Exception('ASO reprovado ou faltando laudo.');
                    }

                    if (Carbon::parse($admission->aso_date)->diffInDays(now()) > 30) {
                        throw new Exception('ASO expirado (mais de 30 dias).');
                    }

                    $admission->status = 'esocial_registration';
                    break;

                case 'esocial_registration':
                    $startDate = Carbon::parse($admission->start_date);
                    $now = Carbon::now();

                    if (! $admission->esocial_receipt) {
                        // AI_RULE_CRITICAL: O evento eSocial S-2200 DEVE ser enviado ate 1 dia util antes da data de inicio.
                        // Para simplificar no teste, 24 horas antes do inicio.
                        if ($startDate->isFuture() && $now->diffInHours($startDate, false) < 24) {
                            SystemAlert::create([
                                'tenant_id' => $admission->tenant_id,
                                'alert_type' => 'esocial_s2200_delayed',
                                'severity' => 'critical',
                                'title' => '🚨 eSocial S-2200 Atrasado',
                                'message' => "Admissão #{$admission->id} com início amanhã precisa do recibo eSocial urgentemente.",
                            ]);

                            throw new Exception('BLOQUEIO LEGAL: S-2200 deve ser enviado até 1 dia útil antes do início.');
                        }

                        throw new Exception('Recibo S-2200 do gov.br pendente.');
                    }

                    $admission->status = 'access_creation';
                    break;

                case 'access_creation':
                    if (! $admission->user_id || ! $admission->email_provisioned || ! $admission->role_assigned) {
                        throw new Exception('Provisionamento de acesso incompleto (Usuário, E-mail ou Role pendentes).');
                    }
                    $admission->status = 'training';
                    break;

                case 'training':
                    if (! $admission->mandatory_trainings_completed) {
                        throw new Exception('Treinamentos obrigatórios do Onboarding não concluídos.');
                    }
                    $admission->status = 'active';
                    break;

                case 'active':
                    throw new Exception('A admissão já está concluída.');
            }

            $admission->save();

            return $admission;
        });
    }
}
