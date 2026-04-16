<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Http\Requests\Settings\UploadLogoRequest;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\TenantSetting;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    use ResolvesCurrentTenant;

    // ── Configurações ──

    public function index(Request $request): JsonResponse
    {
        $query = SystemSetting::query();
        if ($group = $request->get('group')) {
            $query->where('group', $group);
        }

        return ApiResponse::paginated($query->orderBy('group')->orderBy('key')->paginate(min((int) request()->input('per_page', 25), 100)));
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $rawContent = trim((string) $request->getContent());

        if (! $request->has('settings') && in_array($rawContent, ['', '{}', '[]'], true)) {
            throw ValidationException::withMessages([
                'settings' => 'O campo settings é obrigatório.',
            ]);
        }

        $validated = $request->validated();

        if (! isset($validated['settings']) || ! is_array($validated['settings']) || $validated['settings'] === []) {
            throw ValidationException::withMessages([
                'settings' => 'O campo settings é obrigatório.',
            ]);
        }

        try {
            $saved = DB::transaction(function () use ($validated) {
                $saved = [];
                foreach ($validated['settings'] as $item) {
                    $saved[] = SystemSetting::setValue(
                        $item['key'],
                        $item['value'],
                        $item['type'] ?? 'string',
                        $item['group'] ?? 'general'
                    );
                }

                return $saved;
            });

            AuditLog::log('updated', 'Configurações atualizadas');

            return ApiResponse::data($saved);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Settings update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar configurações', 500);
        }
    }

    // ── Upload Logo ──

    public function uploadLogo(UploadLogoRequest $request): JsonResponse
    {
        try {
            $tenantId = $this->resolvedTenantId();

            $oldLogo = TenantSetting::getValue($tenantId, 'company_logo_url');
            if ($oldLogo) {
                $oldPath = str_replace('/storage/', '', $oldLogo);
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('logo')->store("tenants/{$tenantId}/logo", 'public');
            $url = '/storage/'.$path;

            TenantSetting::setValue($tenantId, 'company_logo_url', $url);

            AuditLog::log('updated', 'Logo da empresa atualizado');

            return ApiResponse::data(['url' => $url]);
        } catch (\Throwable $e) {
            Log::error('Logo upload failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao enviar logo', 500);
        }
    }

    // ── Audit Logs ──

    public function auditLogs(Request $request): JsonResponse
    {
        $query = AuditLog::with('user:id,name');

        if ($action = $request->get('action')) {
            $query->where('action', $action);
        }
        if ($userId = $request->get('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($type = $request->get('auditable_type')) {
            $query->where('auditable_type', 'like', SearchSanitizer::contains($type));
        }
        if ($auditableId = $request->get('auditable_id')) {
            $query->where('auditable_id', $auditableId);
        }
        if ($search = $request->get('search')) {
            $safe = SearchSanitizer::contains($search);
            $query->where(function ($q) use ($safe) {
                $q->where('description', 'like', $safe)
                    ->orWhere('old_values', 'like', $safe)
                    ->orWhere('new_values', 'like', $safe);
            });
        }
        if ($from = $request->get('from') ?? $request->get('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->get('to') ?? $request->get('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->orderByDesc('created_at')
            ->paginate(min((int) $request->get('per_page', 50), 100));

        return ApiResponse::paginated($logs);
    }
}
