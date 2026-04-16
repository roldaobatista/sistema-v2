<?php

namespace App\Support;

use App\Enums\PaymentTerms;
use App\Models\Quote;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

final class QuotePaymentSummary
{
    /**
     * @return array{
     *     method_label: string,
     *     condition_summary: string,
     *     detail_text: ?string,
     *     schedule: array<int, array{title: string, days: int, due_date: ?string, text: string}>
     * }
     */
    public static function fromQuote(Quote $quote): array
    {
        $days = self::extractDays($quote->payment_terms_detail, $quote->payment_terms);
        // Use a data mais relevante para o cronograma: envio > aprovação > criação
        $scheduleBaseDate = $quote->sent_at ?? $quote->approved_at ?? $quote->created_at;
        $schedule = self::buildSchedule($days, $scheduleBaseDate);
        $methodLabel = self::resolveMethodLabel($quote->payment_terms, $quote->payment_terms_detail);
        $conditionSummary = self::buildConditionSummary($days, $quote->payment_terms, $quote->payment_terms_detail);
        $detailText = self::buildDetailText($quote->payment_terms_detail, $schedule);

        return [
            'method_label' => $methodLabel,
            'condition_summary' => $conditionSummary,
            'detail_text' => $detailText,
            'schedule' => $schedule,
        ];
    }

    /**
     * @return array<int, int>
     */
    private static function extractDays(?string $detail, ?string $paymentTerms): array
    {
        $candidates = array_values(array_filter([$detail, $paymentTerms]));

        foreach ($candidates as $candidate) {
            $days = self::parseDaysFromString($candidate);
            if ($days !== []) {
                return $days;
            }
        }

        return [];
    }

    /**
     * @return array<int, int>
     */
    private static function parseDaysFromString(string $value): array
    {
        // Skip strings that look like monetary values to avoid false positives
        $lowerValue = mb_strtolower($value);
        if (preg_match('/r\s*\$|reais|valor|total|preco|preço/', $lowerValue)) {
            return [];
        }

        $digitsOnly = preg_replace('/\D+/', '', Str::of($value)->ascii()->value()) ?? '';
        // Only attempt 2-digit grouping for strings like "3060" (30/60 days) or "306090" (30/60/90 days)
        // Require ≥ 4 digits, even length, and at least 2 valid groups to avoid false positives with years
        if ($digitsOnly !== '' && strlen($digitsOnly) >= 4 && strlen($digitsOnly) % 2 === 0) {
            $groupedDays = self::deduplicateAndSort(array_values(array_filter(
                array_map(static fn (string $part): int => (int) $part, str_split($digitsOnly, 2)),
                static fn (int $part): bool => $part > 0 && $part <= 365,
            )));

            // Require at least 2 valid groups to avoid interpreting "2024" as [20, 24]
            if (count($groupedDays) >= 2) {
                return $groupedDays;
            }
        }

        $normalized = Str::of($value)
            ->lower()
            ->ascii()
            ->replace(['apos a emissao', 'apos emissao', 'dias', 'dia', 'parcelas:', 'parcela:'], ' ')
            ->replace(['_', '-', ';'], '/')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();

        preg_match_all('/\d{1,3}/', $normalized, $matches);
        $numbers = array_map(static fn (string $part): int => (int) $part, $matches[0]);
        $numbers = array_values(array_filter($numbers, static fn (int $part): bool => $part > 0 && $part <= 365));

        if ($numbers !== []) {
            return self::deduplicateAndSort($numbers);
        }

        return [];
    }

    /**
     * @param  array<int, int>  $days
     * @return array<int, int>
     */
    private static function deduplicateAndSort(array $days): array
    {
        $unique = array_values(array_unique($days));
        sort($unique);

        return $unique;
    }

    /**
     * @param  array<int, int>  $days
     * @return array<int, array{title: string, days: int, due_date: ?string, text: string}>
     */
    private static function buildSchedule(array $days, ?CarbonInterface $issuedAt): array
    {
        return array_map(static function (int $dayOffset, int $index) use ($issuedAt): array {
            $title = ($index + 1).'a parcela';
            $dueDate = $issuedAt?->copy()->startOfDay()->addDays($dayOffset)->format('d/m/Y');
            $text = "{$title}: {$dayOffset} dias após emissão";

            if ($dueDate) {
                $text .= " ({$dueDate})";
            }

            return [
                'title' => $title,
                'days' => $dayOffset,
                'due_date' => $dueDate,
                'text' => $text,
            ];
        }, $days, array_keys($days));
    }

    private static function resolveMethodLabel(?string $paymentTerms, ?string $detail): string
    {
        $raw = trim((string) ($paymentTerms ?? ''));
        $enum = $raw !== '' ? PaymentTerms::tryFrom($raw) : null;
        if ($enum) {
            return match ($enum) {
                PaymentTerms::BOLETO_30,
                PaymentTerms::BOLETO_30_60,
                PaymentTerms::BOLETO_30_60_90 => 'Boleto bancário',
                PaymentTerms::PARCELADO_2X,
                PaymentTerms::PARCELADO_3X,
                PaymentTerms::PARCELADO_6X,
                PaymentTerms::PARCELADO_10X,
                PaymentTerms::PARCELADO_12X,
                PaymentTerms::CARTAO => 'Cartão de crédito',
                PaymentTerms::PIX => 'PIX',
                PaymentTerms::A_VISTA => 'À vista',
                PaymentTerms::A_COMBINAR => 'A combinar',
                PaymentTerms::PERSONALIZADO => 'A combinar',
            };
        }

        $probe = Str::of(trim($raw !== '' ? $raw : (string) ($detail ?? '')))
            ->lower()
            ->ascii()
            ->replace(['_', '-'], ' ')
            ->value();

        return match (true) {
            str_contains($probe, 'boleto') => 'Boleto bancário',
            str_contains($probe, 'pix') => 'PIX',
            str_contains($probe, 'cartao') || str_contains($probe, 'credito') || str_contains($probe, 'parcelado') => 'Cartão de crédito',
            str_contains($probe, 'vista') => 'À vista',
            str_contains($probe, 'combinar') => 'A combinar',
            default => 'A combinar',
        };
    }

    private static function buildConditionSummary(array $days, ?string $paymentTerms, ?string $detail): string
    {
        if ($days !== []) {
            $count = count($days);

            if ($count === 1) {
                return 'Pagamento em parcela única com vencimento programado após a emissão.';
            }

            return "Pagamento em {$count} parcelas com vencimentos programados após a emissão.";
        }

        $enum = $paymentTerms ? PaymentTerms::tryFrom($paymentTerms) : null;

        return match ($enum) {
            PaymentTerms::A_VISTA => 'Pagamento integral no ato da aprovação.',
            PaymentTerms::PIX => 'Pagamento instantâneo via PIX.',
            PaymentTerms::CARTAO => 'Pagamento via cartão de crédito.',
            PaymentTerms::A_COMBINAR => 'Condição comercial a combinar com o cliente.',
            default => $detail
                ? trim($detail)
                : ($enum?->label() ? "Condição comercial: {$enum->label()}." : 'Condição comercial a combinar com o cliente.'),
        };
    }

    private static function buildDetailText(?string $detail, array $schedule): ?string
    {
        $normalizedDetail = trim((string) $detail);
        if ($normalizedDetail === '') {
            return null;
        }

        if ($schedule !== [] && self::parseDaysFromString($normalizedDetail) !== []) {
            return null;
        }

        return $normalizedDetail;
    }
}
