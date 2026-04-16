<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function data(mixed $data, int $status = 200, array $extra = []): JsonResponse
    {
        return response()->json([
            'data' => $data,
            ...$extra,
        ], $status);
    }

    public static function message(string $message, int $status = 200, array $extra = []): JsonResponse
    {
        return response()->json([
            'message' => $message,
            ...$extra,
        ], $status);
    }

    public static function paginated(LengthAwarePaginator|Paginator $paginator, array $meta = [], array $extra = [], ?string $resourceClass = null): JsonResponse
    {
        $baseMeta = [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
        ];

        if ($paginator instanceof LengthAwarePaginator) {
            $baseMeta['total'] = $paginator->total();
            $baseMeta['last_page'] = $paginator->lastPage();
            $baseMeta['from'] = $paginator->firstItem();
            $baseMeta['to'] = $paginator->lastItem();
        }

        $mergedMeta = array_merge($baseMeta, $meta);

        $legacy = [];
        foreach (['current_page', 'per_page', 'total', 'last_page', 'from', 'to'] as $key) {
            if (array_key_exists($key, $mergedMeta)) {
                $legacy[$key] = $mergedMeta[$key];
            }
        }

        $items = $resourceClass
            ? $resourceClass::collection($paginator->getCollection())->resolve()
            : $paginator->items();

        return response()->json([
            'data' => $items,
            'meta' => $mergedMeta,
            ...$legacy,
            ...$extra,
        ]);
    }

    public static function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }
}
