<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserFavorite\FavoriteRequest;
use App\Models\UserFavorite;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserFavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $favorites = UserFavorite::whereBelongsTo($request->user(), 'user')
            ->where('favoritable_type', $request->query('type', 'App\\Models\\WorkOrder'))
            ->pluck('favoritable_id');

        return ApiResponse::data($favorites);
    }

    public function store(FavoriteRequest $request): JsonResponse
    {
        $validated = $request->validated();

        UserFavorite::firstOrCreate([
            'user_id' => $request->user()->id,
            'favoritable_type' => $validated['favoritable_type'],
            'favoritable_id' => $validated['favoritable_id'],
        ]);

        return ApiResponse::data(null, 201, ['message' => 'Favorito adicionado']);
    }

    public function destroy(FavoriteRequest $request): JsonResponse
    {
        $validated = $request->validated();

        UserFavorite::whereBelongsTo($request->user(), 'user')
            ->where('favoritable_type', $validated['favoritable_type'])
            ->where('favoritable_id', $validated['favoritable_id'])
            ->delete();

        return ApiResponse::noContent();
    }
}
