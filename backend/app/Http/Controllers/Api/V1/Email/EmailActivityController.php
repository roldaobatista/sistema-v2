<?php

namespace App\Http\Controllers\Api\V1\Email;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmailActivityResource;
use App\Models\Email;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class EmailActivityController extends Controller
{
    public function index(Email $email): JsonResponse
    {
        try {
            $this->authorize('view', $email);

            $activities = $email->activities()
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->paginate(max(1, min(request()->integer('per_page', 25), 100)));

            return ApiResponse::paginated($activities, resourceClass: EmailActivityResource::class);
        } catch (AuthorizationException $e) {
            return ApiResponse::message('Sem permissão.', 403);
        } catch (\Exception $e) {
            Log::error('EmailActivity index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar atividades.', 500);
        }
    }
}
