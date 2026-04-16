<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class LookupValueResolver
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, string>  $fallback
     * @return array<int, string>
     */
    public static function allowedValues(string $modelClass, array $fallback, int $tenantId): array
    {
        $fallbackValues = array_values(array_unique([
            ...array_keys($fallback),
            ...array_values($fallback),
        ]));

        $table = (new $modelClass)->getTable();
        if (! Schema::hasTable($table)) {
            return $fallbackValues;
        }

        /** @var array<int, string> $lookupValues */
        $lookupValues = $modelClass::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get(['slug', 'name'])
            ->flatMap(fn (Model $item): array => [
                (string) $item->getAttribute('slug'),
                (string) $item->getAttribute('name'),
            ])
            ->filter(fn (string $value): bool => trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->values()
            ->all();

        return array_values(array_unique([
            ...$lookupValues,
            ...$fallbackValues,
        ]));
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, string>  $fallback
     */
    public static function canonicalValue(string $modelClass, array $fallback, int $tenantId, mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $candidate = trim($value);
        if ($candidate === '') {
            return null;
        }

        foreach ($fallback as $slug => $label) {
            if (strcasecmp($candidate, $slug) === 0 || strcasecmp($candidate, $label) === 0) {
                return $slug;
            }
        }

        $table = (new $modelClass)->getTable();
        if (! Schema::hasTable($table)) {
            return $candidate;
        }

        $lookup = $modelClass::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($query) use ($candidate) {
                $query->whereRaw('LOWER(slug) = ?', [mb_strtolower($candidate)])
                    ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($candidate)]);
            })
            ->first(['slug']);

        $slug = $lookup?->getAttribute('slug');

        return is_string($slug) && trim($slug) !== ''
            ? trim($slug)
            : $candidate;
    }
}
