<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Export\BatchPrintRequest;
use App\Http\Requests\Export\ExportCsvRequest;
use App\Http\Requests\Export\ExportCustomersRequest;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BatchExportController extends Controller
{
    private const ENTITIES = [
        'customers' => Customer::class,
        'products' => Product::class,
        'services' => Service::class,
        'equipments' => Equipment::class,
        'work_orders' => WorkOrder::class,
        'quotes' => Quote::class,
    ];

    private const FIELD_MAPS = [
        'customers' => ['id', 'name', 'document', 'email', 'phone', 'segment', 'rating', 'is_active', 'created_at'],
        'products' => ['id', 'code', 'name', 'unit', 'cost_price', 'sell_price', 'stock_qty', 'stock_min', 'is_active', 'created_at'],
        'services' => ['id', 'code', 'name', 'unit', 'sell_price', 'is_active', 'created_at'],
        'equipments' => ['id', 'customer_id', 'tag', 'name', 'brand', 'model', 'serial_number', 'calibration_due_date', 'is_active', 'created_at'],
        'work_orders' => ['id', 'customer_id', 'equipment_id', 'type', 'status', 'priority', 'scheduled_date', 'total', 'created_at'],
        'quotes' => ['id', 'customer_id', 'status', 'total', 'valid_until', 'created_at'],
    ];

    public function entities(): JsonResponse
    {
        try {
            $result = [];
            foreach (self::ENTITIES as $key => $class) {
                $result[] = [
                    'key' => $key,
                    'label' => $this->entityLabel($key),
                    'fields' => self::FIELD_MAPS[$key] ?? [],
                    'count' => $class::count(),
                ];
            }

            return ApiResponse::data($result);
        } catch (\Exception $e) {
            Log::error('BatchExport entities failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar entidades.', 500);
        }
    }

    public function exportCsv(ExportCsvRequest $request): StreamedResponse
    {
        try {
            $validated = $request->validated();
            $entity = $validated['entity'];
            $modelClass = self::ENTITIES[$entity];
            $fields = $validated['fields'] ?? self::FIELD_MAPS[$entity] ?? ['*'];

            $query = $modelClass::query();

            if (! empty($validated['ids'])) {
                $query->whereIn('id', $validated['ids']);
            }

            if (! empty($validated['filters'])) {
                foreach ($validated['filters'] as $field => $value) {
                    $query->where($field, $value);
                }
            }

            $filename = "{$entity}_export_".now()->format('Y-m-d_His').'.csv';

            return response()->streamDownload(function () use ($query, $fields) {
                $handle = fopen('php://output', 'w');
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, $fields, ';');

                $query->select($fields)->chunk(500, function ($rows) use ($handle, $fields) {
                    foreach ($rows as $row) {
                        $data = [];
                        foreach ($fields as $field) {
                            $data[] = $row->{$field} ?? '';
                        }
                        fputcsv($handle, $data, ';');
                    }
                });

                fclose($handle);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        } catch (ValidationException $e) {
            return response()->streamDownload(function () {
                echo 'Validação falhou';
            }, 'error.txt');
        } catch (\Exception $e) {
            Log::error('BatchExport exportCsv failed', ['error' => $e->getMessage()]);

            return response()->streamDownload(function () {
                echo 'Erro na exportação';
            }, 'error.txt');
        }
    }

    public function batchPrint(BatchPrintRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            return ApiResponse::data([
                'entity' => $validated['entity'],
                'ids' => $validated['ids'],
                'pdf_base_url' => $validated['entity'] === 'work_orders'
                    ? '/api/v1/work-orders/{id}/pdf'
                    : '/api/v1/quotes/{id}/pdf',
            ], 200, ['message' => count($validated['ids']).' documento(s) pronto(s) para impressão.']);
        } catch (ValidationException $e) {
            return ApiResponse::message('Validação falhou.', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('BatchExport batchPrint failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro na impressão em lote.', 500);
        }
    }

    public function exportCustomers(ExportCustomersRequest $request): Response
    {
        $request->validated();
        $user = $request->user();
        $tenantId = (int) ($user->current_tenant_id ?? $user->tenant_id);
        $filename = 'customers_export_'.now()->format('Y-m-d_His').'.csv';
        $fields = ['id', 'name', 'document', 'email', 'phone', 'address_city', 'address_state', 'created_at'];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $fields, ';');

        Customer::query()
            ->where('tenant_id', $tenantId)
            ->select($fields)
            ->chunk(500, function ($rows) use ($handle, $fields): void {
                foreach ($rows as $row) {
                    fputcsv($handle, array_map(fn (string $field) => $row->{$field} ?? '', $fields), ';');
                }
            });

        rewind($handle);
        $contents = stream_get_contents($handle) ?: '';
        fclose($handle);

        return response($contents, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportWorkOrders(Request $request): Response
    {
        $user = $request->user();
        $tenantId = (int) ($user->current_tenant_id ?? $user->tenant_id);
        $filename = 'work_orders_export_'.now()->format('Y-m-d_His').'.csv';
        $fields = ['number', 'status', 'priority', 'customer_name', 'assignee_name', 'total', 'created_at', 'completed_at'];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $fields, ';');

        WorkOrder::query()
            ->with(['customer:id,name', 'assignee:id,name'])
            ->where('tenant_id', $tenantId)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->get('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->get('priority')))
            ->orderByDesc('created_at')
            ->chunk(500, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    $customerName = $row->customer instanceof Customer ? $row->customer->name : '';
                    $assigneeName = $row->assignee instanceof User ? $row->assignee->name : '';

                    fputcsv($handle, [
                        $row->os_number ?? $row->number,
                        $row->status,
                        $row->priority,
                        $customerName,
                        $assigneeName,
                        $row->total ?? '',
                        $this->formatDateTime($row->created_at),
                        $this->formatDateTime($row->completed_at),
                    ], ';');
                }
            });

        rewind($handle);
        $contents = stream_get_contents($handle) ?: '';
        fclose($handle);

        return response($contents, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function entityLabel(string $key): string
    {
        return match ($key) {
            'customers' => 'Clientes',
            'products' => 'Produtos',
            'services' => 'Serviços',
            'equipments' => 'Equipamentos',
            'work_orders' => 'Ordens de Serviço',
            'quotes' => 'Orçamentos',
            default => ucfirst($key),
        };
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return '';
    }
}
