<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\AccountingReportExportRequest;
use App\Http\Requests\Report\AccountingReportIndexRequest;
use App\Models\JourneyEntry;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;

class AccountingReportController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(AccountingReportIndexRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $query = JourneyEntry::with('user:id,name')
                ->where('tenant_id', $this->resolvedTenantId())
                ->whereBetween('date', [$validated['start_date'], $validated['end_date']]);

            if (! empty($validated['user_id'])) {
                $query->where('user_id', $validated['user_id']);
            }

            return ApiResponse::paginated($query->paginate(50));
        } catch (ValidationException $e) {
            return ApiResponse::message('Validação falhou.', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('AccountingReport index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar relatório contábil.', 500);
        }
    }

    public function export(AccountingReportExportRequest $request)
    {
        try {
            $validated = $request->validated();
            $entries = JourneyEntry::with('user:id,name')
                ->where('tenant_id', $this->resolvedTenantId())
                ->whereBetween('date', [$validated['start_date'], $validated['end_date']])
                ->get();

            if ($validated['format'] === 'csv') {
                $headers = [
                    'Content-type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename=relatorio_contabil.csv',
                    'Pragma' => 'no-cache',
                    'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                    'Expires' => '0',
                ];

                $callback = function () use ($entries) {
                    $file = fopen('php://output', 'w');
                    if ($file === false) {
                        return;
                    }

                    fwrite($file, "\xEF\xBB\xBF");

                    fputcsv($file, ['Colaborador', 'Data', 'Jornada Prevista', 'Trabalhado', 'HE 50%', 'HE 100%', 'Ad. Noturno', 'Faltas', 'Banco de Horas']);

                    foreach ($entries as $entry) {
                        fputcsv($file, [
                            $entry->user->name,
                            $entry->date,
                            $entry->scheduled_hours,
                            $entry->worked_hours,
                            $entry->overtime_hours_50,
                            $entry->overtime_hours_100,
                            $entry->night_hours,
                            $entry->absence_hours,
                            $entry->hour_bank_balance,
                        ]);
                    }
                    fclose($file);
                };

                return Response::stream($callback, 200, $headers);
            }

            return ApiResponse::data($entries);
        } catch (ValidationException $e) {
            return ApiResponse::message('Validação falhou.', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('AccountingReport export failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao exportar relatório.', 500);
        }
    }
}
