<?php

namespace App\Http\Controllers\Api\V1\Email;

use App\Http\Controllers\Controller;
use App\Http\Requests\Email\StoreEmailAccountRequest;
use App\Http\Requests\Email\UpdateEmailAccountRequest;
use App\Jobs\SyncEmailAccountJob;
use App\Models\EmailAccount;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Client;

class EmailAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $accounts = EmailAccount::where('tenant_id', $request->user()->current_tenant_id)
            ->orderBy('label')
            ->paginate(min((int) request()->input('per_page', 25), 100))
            ->map(fn ($a) => $a->makeHidden('imap_password'));

        return ApiResponse::data($accounts);
    }

    public function show(Request $request, EmailAccount $emailAccount): JsonResponse
    {
        $this->authorizeTenant($request, $emailAccount);

        return ApiResponse::data($emailAccount->makeHidden('imap_password'));
    }

    public function store(StoreEmailAccountRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $account = EmailAccount::create(array_merge(
                $validated,
                ['tenant_id' => $request->user()->current_tenant_id]
            ));

            DB::commit();

            return ApiResponse::data($account->makeHidden('imap_password'), 201, ['message' => 'Conta de email criada com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Email account creation failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar conta de email', 500);
        }
    }

    public function update(UpdateEmailAccountRequest $request, EmailAccount $emailAccount): JsonResponse
    {
        $this->authorizeTenant($request, $emailAccount);

        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $emailAccount->update($validated);
            DB::commit();

            return ApiResponse::data($emailAccount->fresh()->makeHidden('imap_password'), 200, ['message' => 'Conta de email atualizada']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Email account update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar conta', 500);
        }
    }

    public function destroy(Request $request, EmailAccount $emailAccount): JsonResponse
    {
        $this->authorizeTenant($request, $emailAccount);

        $emailCount = $emailAccount->emails()->count();
        if ($emailCount > 0) {
            return ApiResponse::message("Esta conta possui {$emailCount} emails sincronizados. Desative em vez de excluir.", 409);
        }

        $emailAccount->delete();

        return ApiResponse::message('Conta de email removida');
    }

    public function syncNow(Request $request, EmailAccount $emailAccount): JsonResponse
    {
        $this->authorizeTenant($request, $emailAccount);

        if (! $emailAccount->is_active) {
            return ApiResponse::message('Conta inativa', 422);
        }

        if ($emailAccount->sync_status === 'syncing') {
            return ApiResponse::message('Sincronização já em andamento', 422);
        }

        SyncEmailAccountJob::dispatch($emailAccount);

        return ApiResponse::message('Sincronização iniciada');
    }

    public function testConnection(Request $request, EmailAccount $emailAccount): JsonResponse
    {
        $this->authorizeTenant($request, $emailAccount);

        try {
            $client = new Client([
                'host' => $emailAccount->imap_host,
                'port' => $emailAccount->imap_port,
                'encryption' => $emailAccount->imap_encryption,
                'username' => $emailAccount->imap_username,
                'password' => $emailAccount->imap_password,
                'protocol' => 'imap',
                'validate_cert' => false,
            ]);
            $client->connect();
            $folders = $client->getFolders();
            $client->disconnect();

            return ApiResponse::message(
                'Conexão bem-sucedida',
                200,
                ['folders' => collect($folders)->pluck('name')]
            );
        } catch (\Exception $e) {
            Log::error('Email account connection test failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Falha na conexão com o servidor de e-mail.', 422);
        }
    }

    private function authorizeTenant(Request $request, EmailAccount $emailAccount): void
    {
        abort_if(
            $emailAccount->tenant_id !== $request->user()->current_tenant_id,
            403,
            'Acesso negado'
        );
    }
}
