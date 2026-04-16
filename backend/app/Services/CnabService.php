<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\Tenant;
use Carbon\Carbon;

/**
 * CNAB 240 file generation for batch payroll payments.
 * Follows FEBRABAN standard layout.
 */
class CnabService
{
    /**
     * Generate CNAB 240 file content for a payroll.
     */
    public function generateCnab240(Payroll $payroll): string
    {
        $payroll->load(['lines.user']);
        $tenant = Tenant::findOrFail($payroll->tenant_id);

        $lines = [];

        // File Header (Record Type 0)
        $lines[] = $this->fileHeader240($tenant, $payroll);

        // Batch Header (Record Type 1)
        $lines[] = $this->batchHeader240($tenant, $payroll);

        // Detail records (Record Type 3) — Segment A + Segment B per employee
        $sequenceInBatch = 1;
        foreach ($payroll->lines as $line) {
            if (! $line->user || (float) $line->net_salary <= 0) {
                continue;
            }
            $lines[] = $this->detailSegmentA240($line, $sequenceInBatch++);
            $lines[] = $this->detailSegmentB240($line, $sequenceInBatch++);
        }

        // Batch Trailer (Record Type 5)
        $lines[] = $this->batchTrailer240($payroll, $sequenceInBatch);

        // File Trailer (Record Type 9)
        $lines[] = $this->fileTrailer240(count($lines) + 1);

        return implode("\r\n", $lines);
    }

    /**
     * Generate CNAB 400 file content for a payroll (legacy format).
     */
    public function generateCnab400(Payroll $payroll): string
    {
        $payroll->load(['lines.user']);
        $tenant = Tenant::findOrFail($payroll->tenant_id);

        $lines = [];

        // Header (Record Type 0)
        $lines[] = $this->header400($tenant);

        // Detail records (Record Type 1)
        $sequence = 2;
        foreach ($payroll->lines as $line) {
            if (! $line->user || (float) $line->net_salary <= 0) {
                continue;
            }
            $lines[] = $this->detail400($line, $sequence++);
        }

        // Trailer (Record Type 9)
        $lines[] = $this->trailer400($sequence);

        return implode("\r\n", $lines);
    }

    // ─── CNAB 240 Record Builders ────────────────────────────────

    private function fileHeader240(Tenant $tenant, Payroll $payroll): string
    {
        $cnpj = str_pad(preg_replace('/\D/', '', $tenant->document ?? ''), 14, '0', STR_PAD_LEFT);
        $name = str_pad(mb_substr($tenant->name ?? '', 0, 30), 30);
        $bankCode = '001'; // Default: Banco do Brasil
        $bankName = str_pad('BANCO DO BRASIL', 30);
        $date = now()->format('dmY');
        $time = now()->format('His');
        $fileSequence = str_pad('1', 6, '0', STR_PAD_LEFT);

        return str_pad(
            $bankCode       // 1-3: Bank code
            .'0000'        // 4-7: Service lot
            .'0'           // 8: Record type (0=file header)
            .str_repeat(' ', 9) // 9-17: Filler
            .'2'           // 18: Inscription type (2=CNPJ)
            .$cnpj         // 19-32: CNPJ
            .str_repeat(' ', 20) // 33-52: Agreement code
            .str_repeat(' ', 5)  // 53-57: Agency
            .str_repeat(' ', 1)  // 58: Digit
            .str_repeat(' ', 12) // 59-70: Account
            .str_repeat(' ', 1)  // 71: Digit
            .str_repeat(' ', 1)  // 72: Digit
            .$name          // 73-102: Company name
            .$bankName      // 103-132: Bank name
            .str_repeat(' ', 10) // 133-142: Filler
            .'1'            // 143: File code (1=remessa)
            .$date          // 144-151: Generation date
            .$time          // 152-157: Generation time
            .$fileSequence  // 158-163: File sequence
            .'089'          // 164-166: Layout version
            .str_repeat(' ', 5)  // 167-171: Record density
            .str_repeat(' ', 69), // 172-240: Reserved
            240
        );
    }

    private function batchHeader240(Tenant $tenant, Payroll $payroll): string
    {
        $cnpj = str_pad(preg_replace('/\D/', '', $tenant->document ?? ''), 14, '0', STR_PAD_LEFT);

        return str_pad(
            '001'            // 1-3: Bank code
            .'0001'        // 4-7: Lot number
            .'1'           // 8: Record type (1=batch header)
            .'C'           // 9: Operation type (C=credit)
            .'30'          // 10-11: Service type (30=salary payment)
            .'01'          // 12-13: Payment form
            .'045'         // 14-16: Layout version
            .' '           // 17: Filler
            .'2'           // 18: Inscription type
            .$cnpj         // 19-32: CNPJ
            .str_repeat(' ', 148), // 33-240: Remaining fields
            240
        );
    }

    private function detailSegmentA240($line, int $sequence): string
    {
        $cpf = str_pad(preg_replace('/\D/', '', $line->user->cpf ?? ''), 14, '0', STR_PAD_LEFT);
        $name = str_pad(mb_substr($line->user->name ?? '', 0, 30), 30);
        $value = str_pad(str_replace(['.', ','], '', number_format((float) $line->net_salary, 2, '', '')), 15, '0', STR_PAD_LEFT);
        $date = Carbon::parse($line->payroll->reference_month.'-01')->endOfMonth()->format('dmY');

        return str_pad(
            '001'                                      // 1-3: Bank code
            .'0001'                                   // 4-7: Lot number
            .'3'                                      // 8: Record type (3=detail)
            .str_pad((string) $sequence, 5, '0', STR_PAD_LEFT)  // 9-13: Sequence in lot
            .'A'                                      // 14: Segment code
            .'0'                                      // 15: Movement type
            .'00'                                     // 16-17: Instruction code
            .str_repeat(' ', 3)                       // 18-20: Clearing house code
            .str_repeat('0', 5)                       // 21-25: Beneficiary bank
            .str_repeat(' ', 6)                       // 26-31: Agency
            .str_repeat(' ', 13)                      // 32-44: Account
            .$name                                    // 45-74: Beneficiary name
            .str_repeat(' ', 20)                      // 75-94: Document number
            .$date                                    // 95-102: Payment date
            .'BRL'                                    // 103-105: Currency
            .str_repeat('0', 15)                      // 106-120: Quantity
            .$value                                   // 121-135: Payment value
            .str_repeat(' ', 105),                    // 136-240: Remaining
            240
        );
    }

    private function detailSegmentB240($line, int $sequence): string
    {
        $cpf = str_pad(preg_replace('/\D/', '', $line->user->cpf ?? ''), 14, '0', STR_PAD_LEFT);

        return str_pad(
            '001'
            .'0001'
            .'3'
            .str_pad((string) $sequence, 5, '0', STR_PAD_LEFT)
            .'B'                                      // Segment B
            .str_repeat(' ', 3)                       // Filler
            .'1'                                      // Inscription type (1=CPF)
            .$cpf                                     // CPF
            .str_repeat(' ', 207),                    // Remaining
            240
        );
    }

    private function batchTrailer240(Payroll $payroll, int $recordCount): string
    {
        $totalValue = str_pad(str_replace(['.', ','], '', number_format((float) $payroll->total_net, 2, '', '')), 18, '0', STR_PAD_LEFT);

        return str_pad(
            '001'
            .'0001'
            .'5'                                      // Record type (5=batch trailer)
            .str_repeat(' ', 9)                       // Filler
            .str_pad((string) $recordCount, 6, '0', STR_PAD_LEFT)
            .$totalValue
            .str_repeat('0', 18)
            .str_repeat(' ', 171),
            240
        );
    }

    private function fileTrailer240(int $totalRecords): string
    {
        return str_pad(
            '001'
            .'9999'
            .'9'                                      // Record type (9=file trailer)
            .str_repeat(' ', 9)                       // Filler
            .'000001'                                 // Batch count
            .str_pad((string) $totalRecords, 6, '0', STR_PAD_LEFT)
            .str_repeat(' ', 211),
            240
        );
    }

    // ─── CNAB 400 Record Builders ────────────────────────────────

    private function header400(Tenant $tenant): string
    {
        $cnpj = str_pad(preg_replace('/\D/', '', $tenant->document ?? ''), 14, '0', STR_PAD_LEFT);
        $name = str_pad(mb_substr($tenant->name ?? '', 0, 30), 30);
        $date = now()->format('dmy');

        return str_pad(
            '0'             // 1: Record type
            .'1'           // 2: Operation (1=remessa)
            .'REMESSA'     // 3-9: Literal
            .'01'          // 10-11: Service type
            .str_pad('PAGAMENTO', 15) // 12-26: Service
            .str_repeat(' ', 4)  // 27-30: Agency
            .str_repeat(' ', 8)  // 31-38: Account
            .str_repeat(' ', 6)  // 39-44: Agreement
            .$name          // 45-74: Company name
            .'001'          // 75-77: Bank code
            .str_pad('BANCO DO BRASIL', 15) // 78-92: Bank name
            .$date          // 93-98: File date
            .str_repeat(' ', 294), // 99-400: Reserved
            400
        ).'000001'; // Sequence
    }

    private function detail400($line, int $sequence): string
    {
        $cpf = str_pad(preg_replace('/\D/', '', $line->user->cpf ?? ''), 14, '0', STR_PAD_LEFT);
        $name = str_pad(mb_substr($line->user->name ?? '', 0, 30), 30);
        $value = str_pad(str_replace(['.', ','], '', number_format((float) $line->net_salary, 2, '', '')), 13, '0', STR_PAD_LEFT);

        return str_pad(
            '1'              // 1: Record type
            .'01'           // 2-3: Inscription type (CPF)
            .$cpf           // 4-17: CPF
            .$name          // 18-47: Name
            .str_repeat(' ', 13)  // 48-60: Agency/account
            .$value         // 61-73: Value
            .str_repeat(' ', 321), // 74-400: Remaining
            400
        ).str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    private function trailer400(int $sequence): string
    {
        return str_pad(
            '9'              // 1: Record type (9=trailer)
            .str_repeat(' ', 393), // 2-400: Filler
            400
        ).str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
