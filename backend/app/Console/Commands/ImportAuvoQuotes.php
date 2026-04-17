<?php

namespace App\Console\Commands;

use App\Enums\PaymentTerms;
use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\QuoteEquipment;
use App\Models\QuoteItem;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportAuvoQuotes extends Command
{
    protected $signature = 'import:auvo-quotes
        {orcamentos : Path to the orcamentos Excel file}
        {itens : Path to the itens_orcamento Excel file}
        {--tenant= : Tenant ID}
        {--seller= : Default seller user ID}
        {--dry-run : Preview without saving}';

    protected $description = 'Import quotes from Auvo Excel exports into the system';

    private int $tenantId;

    private int $sellerId;

    private bool $dryRun;

    private int $customersCreated = 0;

    private int $customersExisting = 0;

    private int $quotesCreated = 0;

    private int $quotesSkipped = 0;

    private int $itemsCreated = 0;

    private array $errors = [];

    public function handle(): int
    {
        $this->tenantId = (int) $this->option('tenant');
        $this->sellerId = (int) $this->option('seller');
        $this->dryRun = (bool) $this->option('dry-run');

        if (! $this->tenantId || ! $this->sellerId) {
            $this->error('--tenant and --seller are required.');

            return self::FAILURE;
        }

        $orcFile = $this->argument('orcamentos');
        $itensFile = $this->argument('itens');

        if (! file_exists($orcFile)) {
            $this->error("File not found: {$orcFile}");

            return self::FAILURE;
        }
        if (! file_exists($itensFile)) {
            $this->error("File not found: {$itensFile}");

            return self::FAILURE;
        }

        $this->info('=== Importação de Orçamentos do Auvo ===');
        $this->info("Tenant: {$this->tenantId} | Seller: {$this->sellerId} | Dry-run: ".($this->dryRun ? 'SIM' : 'NÃO'));

        $this->info("\n[1/5] Lendo planilhas...");
        $orcData = $this->readOrcamentos($orcFile);
        $itensData = $this->readItens($itensFile);
        $this->info('  Orçamentos: '.count($orcData).' | Itens: '.count($itensData));

        $this->info("\n[2/5] Construindo mapa de clientes...");
        $customerDocMap = $this->buildCustomerDocMap($itensData);
        $this->info('  Clientes com CPF/CNPJ: '.count($customerDocMap));

        $allCustomerNames = collect($orcData)->pluck('customer_name')->unique()->filter()->values();
        $this->info('  Clientes únicos nos orçamentos: '.$allCustomerNames->count());

        if ($this->dryRun) {
            $this->warn("\n[DRY-RUN] Nenhuma alteração será feita no banco.");
            $this->showPreview($orcData, $itensData, $customerDocMap);

            return self::SUCCESS;
        }

        DB::beginTransaction();

        try {
            $this->info("\n[3/5] Criando clientes...");
            $customerIdMap = $this->createCustomers($allCustomerNames, $customerDocMap);
            $this->info("  Criados: {$this->customersCreated} | Já existiam: {$this->customersExisting}");

            $this->info("\n[4/5] Criando orçamentos...");
            $quoteIdMap = $this->createQuotes($orcData, $customerIdMap);
            $this->info("  Criados: {$this->quotesCreated} | Pulados (já existiam): {$this->quotesSkipped}");

            $this->info("\n[5/5] Criando itens dos orçamentos...");
            $this->createQuoteItems($itensData, $quoteIdMap);
            $this->info("  Itens criados: {$this->itemsCreated}");

            if (! empty($this->errors)) {
                $this->warn("\nAvisos/Erros encontrados:");
                foreach (array_slice($this->errors, 0, 20) as $err) {
                    $this->warn("  - {$err}");
                }
                if (count($this->errors) > 20) {
                    $this->warn('  ... e mais '.(count($this->errors) - 20).' erros');
                }
            }

            DB::commit();
            $this->newLine();
            $this->info('========================================');
            $this->info('Importação concluída com sucesso!');
            $this->info("  Clientes: {$this->customersCreated} novos");
            $this->info("  Orçamentos: {$this->quotesCreated} criados");
            $this->info("  Itens: {$this->itemsCreated} criados");

            $nextNumber = Quote::nextNumber($this->tenantId);
            $this->info("  Próximo número: {$nextNumber}");
            $this->info('========================================');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("ERRO FATAL: {$e->getMessage()}");
            $this->error("Linha: {$e->getFile()}:{$e->getLine()}");
            Log::error('ImportAuvoQuotes failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    private function readOrcamentos(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        $data = [];
        // Row 0 = headers, Row 1 = descriptions, data starts at row 2
        for ($i = 2; $i < count($rows); $i++) {
            $r = $rows[$i];
            if (empty($r[0])) {
                continue;
            }

            $data[] = [
                'number' => (int) $r[0],
                'created_at' => $this->parseDatetime($r[1]),
                'seller' => $this->cleanEncoding($r[2] ?? ''),
                'status' => $this->cleanEncoding($r[3] ?? ''),
                'status_updated_at' => $this->parseDatetime($r[4] ?? ''),
                'customer_name' => $this->cleanEncoding(trim($r[5] ?? '')),
                'product_value' => $this->parseMoney($r[6] ?? '0'),
                'service_value' => $this->parseMoney($r[7] ?? '0'),
                'additional_cost' => $this->parseMoney($r[8] ?? '0'),
                'discount' => $this->parseMoney($r[9] ?? '0'),
                'total' => $this->parseMoney($r[10] ?? '0'),
                'observations' => $this->cleanEncoding($r[11] ?? ''),
                'internal_notes' => $this->cleanEncoding($r[12] ?? ''),
                'tasks' => $this->cleanEncoding($r[13] ?? ''),
                'expiration' => $this->cleanEncoding($r[14] ?? ''),
                'payment_form' => $this->cleanEncoding($r[15] ?? ''),
                'payment_condition' => $r[16] ?? '',
                'approval_status' => $this->cleanEncoding($r[17] ?? ''),
                'approval_date' => $this->cleanEncoding($r[18] ?? ''),
                'rejection_reason' => $this->cleanEncoding($r[19] ?? ''),
            ];
        }

        $spreadsheet->disconnectWorksheets();

        return $data;
    }

    private function readItens(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        $data = [];
        // Row 0 = filter info, Row 1 = headers, data starts at row 2
        for ($i = 2; $i < count($rows); $i++) {
            $r = $rows[$i];
            if (empty($r[0])) {
                continue;
            }

            $data[] = [
                'quote_number' => (int) $r[0],
                'created_at' => $this->parseDatetime($r[1] ?? ''),
                'created_by' => $this->cleanEncoding($r[2] ?? ''),
                'customer_name' => $this->cleanEncoding(trim($r[3] ?? '')),
                'customer_doc' => preg_replace('/\D/', '', $r[4] ?? ''),
                'status' => $this->cleanEncoding($r[5] ?? ''),
                'status_date' => $this->parseDatetime($r[6] ?? ''),
                'product_code' => trim($r[7] ?? ''),
                'product_name' => $this->cleanEncoding(trim($r[8] ?? '')),
                'product_description' => $this->cleanEncoding($r[9] ?? ''),
                'unit_price' => $this->parseMoney($r[10] ?? '0'),
                'quantity' => $this->parseNumber($r[11] ?? '1'),
                'discount' => $this->parseMoney($r[12] ?? '0'),
                'item_total' => $this->parseMoney($r[13] ?? '0'),
                'expiration' => $this->cleanEncoding($r[14] ?? ''),
                'approval_status' => $this->cleanEncoding($r[15] ?? ''),
                'approval_date' => $this->cleanEncoding($r[16] ?? ''),
                'rejection_reason' => $this->cleanEncoding($r[17] ?? ''),
            ];
        }

        $spreadsheet->disconnectWorksheets();

        return $data;
    }

    private function buildCustomerDocMap(array $itensData): array
    {
        $map = [];
        foreach ($itensData as $item) {
            $name = $item['customer_name'];
            $doc = $item['customer_doc'];
            if ($name && $doc && strlen($doc) >= 11) {
                $map[$name] = $doc;
            }
        }

        return $map;
    }

    /**
     * @return array<string, int> name → customer_id
     */
    private function createCustomers($names, array $docMap): array
    {
        $idMap = [];

        foreach ($names as $name) {
            if (! $name) {
                continue;
            }

            $doc = $docMap[$name] ?? null;

            // Try to find existing customer by document or name
            $existing = null;
            if ($doc) {
                $existing = Customer::where('tenant_id', $this->tenantId)
                    ->where('document_hash', Customer::hashSearchable('document', $doc))
                    ->first();
            }
            if (! $existing) {
                $existing = Customer::where('tenant_id', $this->tenantId)
                    ->where('name', $name)
                    ->first();
            }

            if ($existing) {
                $idMap[$name] = $existing->id;
                $this->customersExisting++;
                continue;
            }

            $type = 'PJ';
            if ($doc) {
                $type = strlen($doc) <= 11 ? 'PF' : 'PJ';
            }

            $customer = Customer::create([
                'tenant_id' => $this->tenantId,
                'name' => $name,
                'document' => $doc,
                'type' => $type,
                'is_active' => true,
            ]);

            $idMap[$name] = $customer->id;
            $this->customersCreated++;
        }

        return $idMap;
    }

    /**
     * @return array<int, int> auvo_number → quote_id
     */
    private function createQuotes(array $orcData, array $customerIdMap): array
    {
        $quoteIdMap = [];

        foreach ($orcData as $orc) {
            $quoteNumber = 'ORC-'.str_pad((string) $orc['number'], 5, '0', STR_PAD_LEFT);

            $existing = Quote::withTrashed()
                ->where('tenant_id', $this->tenantId)
                ->where('quote_number', $quoteNumber)
                ->first();

            if ($existing) {
                $quoteIdMap[$orc['number']] = $existing->id;
                $this->quotesSkipped++;
                continue;
            }

            $customerId = $customerIdMap[$orc['customer_name']] ?? null;
            if (! $customerId) {
                $this->errors[] = "ORC {$orc['number']}: cliente não encontrado: '{$orc['customer_name']}'";
                continue;
            }

            $status = $this->mapStatus($orc['status'], $orc['approval_status']);
            $paymentTerms = $this->mapPaymentTerms($orc['payment_form']);

            $subtotal = bcadd(
                bcadd($orc['product_value'], $orc['service_value'], 2),
                $orc['additional_cost'],
                2
            );

            $validUntil = $this->parseExpirationDate($orc['expiration']);

            ['approved_at' => $approvedAt, 'rejected_at' => $rejectedAt, 'sent_at' => $sentAt] = $this->resolveLifecycleDates($status, $orc);

            $internalNotes = $orc['internal_notes'];
            if ($orc['tasks']) {
                $internalNotes .= ($internalNotes ? "\n" : '')."Tarefas Auvo: {$orc['tasks']}";
            }
            $internalNotes .= ($internalNotes ? "\n" : '')."[Importado do Auvo - Orç. #{$orc['number']}]";

            $quote = new Quote;
            $quote->tenant_id = $this->tenantId;
            $quote->quote_number = $quoteNumber;
            $quote->revision = 1;
            $quote->customer_id = $customerId;
            $quote->seller_id = $this->sellerId;
            $quote->status = $status;
            $quote->valid_until = $validUntil;
            $quote->discount_percentage = '0.00';
            $quote->discount_amount = $orc['discount'];
            $quote->displacement_value = '0.00';
            $quote->subtotal = $subtotal;
            $quote->total = $orc['total'];
            $quote->currency = 'BRL';
            $quote->observations = $orc['observations'] ?: null;
            $quote->internal_notes = $internalNotes ?: null;
            $quote->payment_terms = $paymentTerms?->value;
            $quote->payment_terms_detail = $orc['payment_condition'] ? "Parcelas: {$orc['payment_condition']}" : null;
            $quote->approved_at = $approvedAt;
            $quote->rejected_at = $rejectedAt;
            $quote->rejection_reason = $orc['rejection_reason'] ?: null;
            $quote->sent_at = $sentAt;
            $quote->created_at = $orc['created_at'];
            $quote->updated_at = $orc['status_updated_at'] ?? $orc['created_at'];
            $quote->saveQuietly();

            $quoteIdMap[$orc['number']] = $quote->id;
            $this->quotesCreated++;
        }

        return $quoteIdMap;
    }

    private function createQuoteItems(array $itensData, array $quoteIdMap): void
    {
        $grouped = collect($itensData)->groupBy('quote_number');
        $equipmentCache = [];

        foreach ($grouped as $quoteNum => $items) {
            $quoteId = $quoteIdMap[(int) $quoteNum] ?? null;
            if (! $quoteId) {
                $this->errors[] = "Itens do ORC {$quoteNum}: orçamento não encontrado no mapa";
                continue;
            }

            if (! isset($equipmentCache[$quoteId])) {
                $equip = QuoteEquipment::create([
                    'tenant_id' => $this->tenantId,
                    'quote_id' => $quoteId,
                    'equipment_id' => null,
                    'description' => 'Itens importados do Auvo',
                    'sort_order' => 1,
                ]);
                $equipmentCache[$quoteId] = $equip->id;
            }

            $equipId = $equipmentCache[$quoteId];
            $sortOrder = 1;

            foreach ($items as $item) {
                $unitPrice = $item['unit_price'];
                $quantity = $item['quantity'] ?: '1.00';
                $discount = $item['discount'];
                $grossTotal = bcmul($unitPrice, $quantity, 2);

                $discountPct = '0.00';
                if (bccomp($grossTotal, '0', 2) > 0 && bccomp($discount, '0', 2) > 0) {
                    $discountPct = bcmul(bcdiv($discount, $grossTotal, 6), '100', 2);
                }

                $description = $item['product_name'];
                if ($item['product_code']) {
                    $description = "[{$item['product_code']}] {$description}";
                }
                if ($item['product_description']) {
                    $description .= " - {$item['product_description']}";
                }
                $description = mb_substr($description, 0, 255);

                $qi = new QuoteItem;
                $qi->tenant_id = $this->tenantId;
                $qi->quote_equipment_id = $equipId;
                $qi->type = 'product';
                $qi->product_id = null;
                $qi->service_id = null;
                $qi->custom_description = $description;
                $qi->quantity = $quantity;
                $qi->original_price = $unitPrice;
                $qi->cost_price = '0.00';
                $qi->unit_price = $unitPrice;
                $qi->discount_percentage = $discountPct;
                $qi->sort_order = $sortOrder++;
                // subtotal is auto-calculated by QuoteItem::saving boot
                $qi->saveQuietly();

                // Manually calculate subtotal since we used saveQuietly
                $price = $unitPrice;
                if (bccomp($discountPct, '0', 2) > 0) {
                    $factor = bcsub('1', bcdiv($discountPct, '100', 6), 6);
                    $price = bcmul($price, $factor, 2);
                }
                $qi->subtotal = bcmul($price, $quantity, 2);
                $qi->saveQuietly();

                $this->itemsCreated++;
            }
        }
    }

    private function mapStatus(string $auvoStatus, string $approvalStatus): QuoteStatus
    {
        $status = mb_strtolower(trim($auvoStatus));
        $approval = mb_strtolower(trim($approvalStatus));

        if (str_contains($status, 'aprovado')) {
            return QuoteStatus::APPROVED;
        }

        if (str_contains($status, 'fatur')) {
            return QuoteStatus::INVOICED;
        }

        if (str_contains($status, 'cancelado')) {
            return QuoteStatus::REJECTED;
        }

        // "Abertos" - map by approval status
        return match (true) {
            str_contains($approval, 'fatur') => QuoteStatus::INVOICED,
            str_contains($approval, 'aprovado') => QuoteStatus::APPROVED,
            str_contains($approval, 'aguardando') => QuoteStatus::SENT,
            str_contains($approval, 'recusado') => QuoteStatus::REJECTED,
            default => QuoteStatus::DRAFT,
        };
    }

    private function resolveLifecycleDates(QuoteStatus $status, array $orc): array
    {
        $statusUpdatedAt = $orc['status_updated_at'] ?? null;
        $approvalDate = $this->parseDatetime($orc['approval_date'] ?? null);
        $createdAt = $orc['created_at'] ?? null;

        $approvedAt = null;
        $rejectedAt = null;
        $sentAt = null;

        if ($status === QuoteStatus::REJECTED) {
            $rejectedAt = $statusUpdatedAt ?? $approvalDate ?? $createdAt;

            return [
                'approved_at' => null,
                'rejected_at' => $rejectedAt,
                'sent_at' => null,
            ];
        }

        if (in_array($status, [QuoteStatus::APPROVED, QuoteStatus::INVOICED], true)) {
            $approvedAt = $approvalDate ?? $statusUpdatedAt ?? $createdAt;
            $sentAt = $createdAt ?? $statusUpdatedAt ?? $approvalDate;

            return [
                'approved_at' => $approvedAt,
                'rejected_at' => null,
                'sent_at' => $sentAt,
            ];
        }

        if ($status === QuoteStatus::SENT) {
            $sentAt = $statusUpdatedAt ?? $createdAt;
        }

        return [
            'approved_at' => null,
            'rejected_at' => null,
            'sent_at' => $sentAt,
        ];
    }

    private function mapPaymentTerms(string $form): ?PaymentTerms
    {
        $form = mb_strtolower(trim($form));

        return match (true) {
            str_contains($form, 'vista') || str_contains($form, 'avista') => PaymentTerms::A_VISTA,
            str_contains($form, 'boleto') => PaymentTerms::BOLETO_30,
            str_contains($form, 'transfer') || str_contains($form, 'pix') => PaymentTerms::PIX,
            str_contains($form, 'cart') => PaymentTerms::CARTAO,
            $form === '' => null,
            default => PaymentTerms::A_COMBINAR,
        };
    }

    private function parseExpirationDate(string $text): ?Carbon
    {
        if (! $text || $text === '-') {
            return null;
        }

        // Format: "A proposta venceu há X dias (DD/MM/YYYY)" or just "DD/MM/YYYY"
        if (preg_match('/(\d{2}\/\d{2}\/\d{4})/', $text, $m)) {
            try {
                return Carbon::createFromFormat('d/m/Y', $m[1]);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function parseDatetime(?string $value): ?Carbon
    {
        if (! $value || $value === '-' || $value === '') {
            return null;
        }

        // "26/08/2025 17:47:37"
        try {
            if (preg_match('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}$/', $value)) {
                return Carbon::createFromFormat('d/m/Y H:i:s', $value);
            }
            // "24/02/2025"
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
                return Carbon::createFromFormat('d/m/Y', $value)->startOfDay();
            }

            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseMoney(?string $value): string
    {
        if (! $value || $value === '' || $value === '-') {
            return '0.00';
        }

        $value = trim($value);

        // Brazilian format: 1.234,56 → 1234.56
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return bcadd($value, '0', 2);
    }

    private function parseNumber(?string $value): string
    {
        if (! $value || $value === '' || $value === '-') {
            return '1.00';
        }

        $value = trim($value);
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return bcadd($value, '0', 2);
    }

    private function cleanEncoding(?string $value): string
    {
        if (! $value) {
            return '';
        }

        // PhpSpreadsheet should handle encoding, but clean up any remaining issues
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        return trim($value);
    }

    private function showPreview(array $orcData, array $itensData, array $docMap): void
    {
        $this->info("\n--- PREVIEW ---");
        $this->info('Orçamentos a criar: '.count($orcData));
        $this->info('Itens a criar: '.count($itensData));

        $this->info("\nPrimeiros 5 orçamentos:");
        foreach (array_slice($orcData, 0, 5) as $orc) {
            $status = $this->mapStatus($orc['status'], $orc['approval_status']);
            $num = 'ORC-'.str_pad((string) $orc['number'], 5, '0', STR_PAD_LEFT);
            $this->line("  {$num} | {$orc['customer_name']} | R\$ {$orc['total']} | {$status->value}");
        }

        $this->info("\nPrimeiros 5 itens:");
        foreach (array_slice($itensData, 0, 5) as $item) {
            $this->line("  ORC-{$item['quote_number']} | [{$item['product_code']}] {$item['product_name']} | {$item['quantity']} x R\$ {$item['unit_price']}");
        }

        $numberRange = collect($orcData)->pluck('number');
        $min = $numberRange->min();
        $max = $numberRange->max();
        $this->info("\nFaixa de numeração: ORC-".str_pad($min, 5, '0', STR_PAD_LEFT).' a ORC-'.str_pad($max, 5, '0', STR_PAD_LEFT));
        $this->info('Próximo número após import: ORC-'.str_pad($max + 1, 5, '0', STR_PAD_LEFT));
    }
}
