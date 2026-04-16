<?php

namespace App\Http\Controllers\Api\V1\Iam;

use App\Events\TechnicianLocationUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Iam\UpdateUserLocationRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserLocationController extends Controller
{
    public function update(UpdateUserLocationRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            DB::beginTransaction();

            $user = $request->user();

            $user->forceFill([
                'location_lat' => $validated['latitude'],
                'location_lng' => $validated['longitude'],
                'location_updated_at' => now(),
            ])->save();

            DB::commit();

            broadcast(new TechnicianLocationUpdated($user));

            return ApiResponse::message('Localização atualizada com sucesso.', 200, [
                'location' => [
                    'lat' => $user->location_lat,
                    'lng' => $user->location_lng,
                    'updated_at' => $user->location_updated_at,
                ],
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::message('Validação falhou', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('UserLocation update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar localização', 500);
        }
    }
}
