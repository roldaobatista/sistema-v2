<?php

namespace App\Http\Controllers\Api\V1\Os;

use App\Enums\ExpenseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Os\ExportWorkOrderCsvRequest;
use App\Http\Requests\Os\ImportWorkOrderCsvRequest;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use App\Traits\ScopesByRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkOrderImportExportController extends Controller
{
    use ResolvesCurrentTenant;
    use ScopesByRole;

    public function exportCsv(ExportWorkOrderCsvRequest $request): StreamedResponse
    {
        $this->authorize('export', WorkOrder::class);
        $tenantId = $this->tenantId();

        $query = WorkOrder::with(['customer:id,name', 'assignee:id,name'])
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($priority = $request->get('priority')) {
            $query->where('priority', $priority);
        }
        if ($assignedTo = $request->get('assigned_to')) {
            $query->where(function ($q) use ($assignedTo) {
                $q->where('assigned_to', $assignedTo)
                    ->orWhereHas('technicians', fn ($t) => $t->where('user_id', $assignedTo));
            });
        }
        if ($from = $request->get('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->get('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $headers = ['Número', 'Status', 'Prioridade', 'Cliente', 'Técnico', 'Total', 'Criado em', 'Concluído em'];

        /** @var list<list<string>> $rows */
        $rows = [];
        foreach ($query->cursor() as $wo) {
            $rows[] = [
                (string) ($wo->business_number ?? ''),
                WorkOrder::STATUSES[$wo->status]['label'] ?? $wo->status,
                WorkOrder::PRIORITIES[$wo->priority]['label'] ?? $wo->priority,
                $wo->customer->name ?? '—',
                $wo->assignee->name ?? '—',
                number_format((float) $wo->total, 2, ',', '.'),
                $wo->created_at?->format('d/m/Y H:i') ?? '',
                $wo->completed_at?->format('d/m/Y H:i') ?? '',
            ];
        }

        if ($request->get('format') === 'xlsx') {
            return $this->exportAsXlsx($headers, $rows);
        }

        $callback = function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                throw new \RuntimeException('Não foi possível abrir o stream de saída CSV.');
            }

            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        };

        $filename = 'os_export_'.now()->format('Y-m-d_His').'.csv';

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }

    public function importCsv(ImportWorkOrderCsvRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $userId = auth()->id();

        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            return ApiResponse::message('Arquivo CSV não enviado ou inválido', 422);
        }

        $path = $file->getRealPath();
        if ($path === false) {
            return ApiResponse::message('Arquivo CSV não pôde ser lido', 422);
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return ApiResponse::message('Arquivo CSV não pôde ser aberto', 422);
        }

        $header = fgetcsv($handle, 0, ';');
        if (! $header) {
            fclose($handle);

            return ApiResponse::message('Arquivo CSV vazio ou inválido', 422);
        }

        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $header);

        $requiredColumns = ['cliente', 'descricao', 'valor_total'];
        $missing = array_diff($requiredColumns, $header);
        if (! empty($missing)) {
            fclose($handle);

            return ApiResponse::message('Colunas obrigatórias faltando: '.implode(', ', $missing), 422, ['expected_columns' => $this->importCsvTemplate()]);
        }

        // Primeira passada: coletar todas as linhas do CSV (limite de 500 rows)
        $rows = [];
        $row = 1;
        $maxRows = 500;
        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            if ($row > $maxRows) {
                fclose($handle);

                return ApiResponse::message("O arquivo excede o limite de {$maxRows} linhas. Divida o arquivo em partes menores.", 422);
            }
            $row++;
            $values = array_map(
                static fn ($value): string => (string) $value,
                array_slice(array_pad($data, count($header), ''), 0, count($header))
            );
            /** @var array<string, string> $line */
            $line = array_combine($header, $values);
            $rows[] = ['row' => $row, 'line' => $line];
        }
        fclose($handle);

        // Pré-carregar clientes e técnicos do tenant em uma única query cada
        $customers = Customer::where('tenant_id', $tenantId)
            ->get(['id', 'name'])
            ->keyBy(fn ($c) => mb_strtolower(trim($c->name)));

        $technicians = User::where('tenant_id', $tenantId)
            ->get(['id', 'name'])
            ->keyBy(fn ($u) => mb_strtolower(trim($u->name)));

        $created = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $customers, $technicians, $tenantId, $userId, &$created, &$errors) {
            foreach ($rows as $entry) {
                $row = $entry['row'];
                $line = $entry['line'];

                try {
                    $customerName = trim($line['cliente'] ?? '');
                    if (! $customerName) {
                        $errors[] = "Linha {$row}: cliente vazio";
                        continue;
                    }

                    $customerLower = mb_strtolower($customerName);
                    $customer = $customers->get($customerLower)
                        ?? $customers->first(fn ($c) => str_contains(mb_strtolower($c->name), $customerLower));

                    if (! $customer) {
                        $errors[] = "Linha {$row}: cliente '{$customerName}' não encontrado";
                        continue;
                    }

                    $techName = trim($line['tecnico'] ?? '');
                    $techId = null;
                    if ($techName) {
                        $techLower = mb_strtolower($techName);
                        $tech = $technicians->get($techLower)
                            ?? $technicians->first(fn ($u) => str_contains(mb_strtolower($u->name), $techLower));
                        $techId = $tech?->id;
                    }

                    $total = $this->parseBrlNumber($line['valor_total'] ?? '0');
                    $status = trim($line['status'] ?? WorkOrder::STATUS_COMPLETED);
                    if (! array_key_exists($status, WorkOrder::STATUSES)) {
                        $status = WorkOrder::STATUS_COMPLETED;
                    }

                    $receivedAt = $this->parseDate($line['data'] ?? $line['data_recebimento'] ?? $line['received_at'] ?? '');
                    $completedAt = $this->parseDate($line['data_conclusao'] ?? $line['completed_at'] ?? '') ?: $receivedAt;

                    $order = WorkOrder::create([
                        'tenant_id' => $tenantId,
                        'customer_id' => $customer->id,
                        'assigned_to' => $techId,
                        'description' => trim($line['descricao'] ?? 'Serviço'),
                        'os_number' => trim($line['numero_os'] ?? $line['os_number'] ?? '') ?: null,
                        'priority' => trim($line['prioridade'] ?? WorkOrder::PRIORITY_MEDIUM),
                        'number' => WorkOrder::nextNumber($tenantId),
                        'created_by' => $userId,
                        'status' => $status,
                        'total' => $total,
                        'received_at' => $receivedAt,
                        'started_at' => $receivedAt,
                        'completed_at' => $completedAt,
                        'displacement_value' => $this->parseBrlNumber($line['deslocamento'] ?? '0'),
                        'discount' => $this->parseBrlNumber($line['desconto'] ?? '0'),
                    ]);

                    $order->statusHistory()->create([
                        'tenant_id' => $tenantId,
                        'user_id' => $userId,
                        'from_status' => null,
                        'to_status' => $status,
                        'notes' => 'Importação CSV retroativa',
                    ]);

                    $itemDesc = trim($line['item_descricao'] ?? '');
                    $itemPrice = $this->parseBrlNumber($line['item_valor'] ?? $line['valor_total'] ?? '0');
                    $itemCost = $this->parseBrlNumber($line['item_custo'] ?? $line['custo'] ?? '0');
                    $itemType = trim($line['item_tipo'] ?? 'service');

                    if ($itemDesc || $itemPrice > 0) {
                        $qty = max(1, (float) ($line['item_qtd'] ?? 1));
                        $order->items()->create([
                            'tenant_id' => $tenantId,
                            'type' => in_array($itemType, ['product', 'service']) ? $itemType : 'service',
                            'description' => $itemDesc ?: trim($line['descricao'] ?? 'Serviço'),
                            'quantity' => $qty,
                            'unit_price' => $itemPrice,
                            'cost_price' => $itemCost,
                            'total' => bcmul((string) $itemPrice, (string) $qty, 2),
                        ]);
                    }

                    $expenseAmount = $this->parseBrlNumber($line['despesa_valor'] ?? '0');
                    if ($expenseAmount > 0) {
                        Expense::create([
                            'tenant_id' => $tenantId,
                            'work_order_id' => $order->id,
                            'description' => trim($line['despesa_descricao'] ?? 'Despesa da OS'),
                            'amount' => $expenseAmount,
                            'expense_date' => $receivedAt ?? now(),
                            'status' => ExpenseStatus::APPROVED->value,
                            'affects_net_value' => true,
                            'created_by' => $userId,
                        ]);
                    }

                    $created++;
                } catch (\Exception $e) {
                    $errors[] = "Linha {$row}: {$e->getMessage()}";
                }
            }
        });

        return ApiResponse::data([
            'message' => "{$created} OS importadas com sucesso".(count($errors) > 0 ? '. '.count($errors).' erro(s)' : ''),
            'created' => $created,
            'errors' => array_slice($errors, 0, 50),
        ]);
    }

    public function importCsvTemplate(): JsonResponse
    {
        $this->authorize('create', WorkOrder::class);
        $columns = [
            ['column' => 'cliente', 'required' => true, 'description' => 'Nome do cliente (busca parcial)'],
            ['column' => 'descricao', 'required' => true, 'description' => 'Descrição do serviço'],
            ['column' => 'valor_total', 'required' => true, 'description' => 'Valor total da OS (ex: 1500,00)'],
            ['column' => 'tecnico', 'required' => false, 'description' => 'Nome do técnico (busca parcial)'],
            ['column' => 'data', 'required' => false, 'description' => 'Data da OS (dd/mm/yyyy)'],
            ['column' => 'data_conclusao', 'required' => false, 'description' => 'Data de conclusão (dd/mm/yyyy)'],
            ['column' => 'numero_os', 'required' => false, 'description' => 'Número da OS original'],
            ['column' => 'status', 'required' => false, 'description' => 'Status: open, completed, delivered, invoiced (padrão: completed)'],
            ['column' => 'prioridade', 'required' => false, 'description' => 'Prioridade: low, normal, high, urgent'],
            ['column' => 'deslocamento', 'required' => false, 'description' => 'Valor de deslocamento'],
            ['column' => 'desconto', 'required' => false, 'description' => 'Valor de desconto'],
            ['column' => 'item_descricao', 'required' => false, 'description' => 'Descrição do item/serviço'],
            ['column' => 'item_tipo', 'required' => false, 'description' => 'Tipo: product ou service (padrão: service)'],
            ['column' => 'item_valor', 'required' => false, 'description' => 'Valor unitário do item'],
            ['column' => 'item_qtd', 'required' => false, 'description' => 'Quantidade do item'],
            ['column' => 'item_custo', 'required' => false, 'description' => 'Custo do item (para cálculo de comissão)'],
            ['column' => 'despesa_valor', 'required' => false, 'description' => 'Valor da despesa vinculada à OS'],
            ['column' => 'despesa_descricao', 'required' => false, 'description' => 'Descrição da despesa'],
        ];

        return ApiResponse::data([
            'separator' => ';',
            'columns' => $columns,
            'example' => 'cliente;descricao;valor_total;tecnico;data;numero_os;item_custo;despesa_valor'."\n"
                .'João Silva;Manutenção preventiva;1500,00;Rodolfo;15/03/2025;OS-001;200,00;50,00',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private function exportAsXlsx(array $headers, array $rows): StreamedResponse
    {
        $filename = 'os_export_'.now()->format('Y-m-d_His').'.xlsx';

        return response()->streamDownload(function () use ($headers, $rows) {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->fromArray($headers, null, 'A1');
            if ($rows !== []) {
                $sheet->fromArray($rows, null, 'A2');
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }

    /** @return numeric-string */
    private function parseBrlNumber(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '0.00';
        }

        $normalized = str_replace(['R$', ' '], '', $raw);
        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }

        $normalized = preg_replace('/[^0-9.-]/', '', $normalized) ?? '';
        if ($normalized === '' || $normalized === '-' || $normalized === '.' || ! is_numeric($normalized)) {
            return '0.00';
        }

        return (string) (float) $normalized;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        foreach (['d/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $raw);
                if ($date !== null) {
                    return $date;
                }
            } catch (\Throwable) {
                // Try the next accepted import format.
            }
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
