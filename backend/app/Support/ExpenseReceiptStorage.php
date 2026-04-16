<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class ExpenseReceiptStorage
{
    public static function store(?UploadedFile $file, int $tenantId): ?string
    {
        if (! $file) {
            return null;
        }

        return self::toPublicPath($file->store("tenants/{$tenantId}/receipts", 'public'));
    }

    public static function toPublicPath(string $path): string
    {
        return '/storage/'.ltrim($path, '/');
    }

    public static function toDiskPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $normalized = ltrim($path, '/');

        return str_starts_with($normalized, 'storage/')
            ? substr($normalized, strlen('storage/'))
            : $normalized;
    }

    public static function deleteQuietly(?string $path, array $context = []): void
    {
        $diskPath = self::toDiskPath($path);

        if (! $diskPath) {
            return;
        }

        try {
            Storage::disk('public')->delete($diskPath);
        } catch (\Throwable $e) {
            Log::warning('Expense receipt deletion failed', [
                'receipt_path' => $path,
                'disk_path' => $diskPath,
                'error' => $e->getMessage(),
                ...$context,
            ]);
        }
    }
}
