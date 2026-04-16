<?php

use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CrmAdvancedController;
use App\Http\Controllers\Api\V1\CrmMessageController;
use App\Http\Controllers\Api\V1\Email\EmailController;
use App\Http\Controllers\Api\V1\FiscalPublicController;
use App\Http\Controllers\Api\V1\FiscalWebhookCallbackController;
use App\Http\Controllers\Api\V1\MetrologyQualityController;
use App\Http\Controllers\Api\V1\Portal\PortalAuthController;
use App\Http\Controllers\Api\V1\Portal\PortalController;
use App\Http\Controllers\Api\V1\Portal\PortalGuestController;
use App\Http\Controllers\Api\V1\Portal\PortalTicketController;
use App\Http\Controllers\Api\V1\PublicWorkOrderTrackingController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\QuotePublicApprovalController;
use App\Http\Controllers\Api\V1\Webhook\WhatsAppWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\PaymentWebhookController;
use App\Http\Controllers\Api\V1\WorkOrderFieldController;
use App\Http\Controllers\Api\V1\WorkOrderRatingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — /api/v1/*
|--------------------------------------------------------------------------
*/

Route::get('/health', HealthCheckController::class);

Route::prefix('v1')->group(function () {

    // --- Auth (público) ---
    Route::middleware('throttle:login')->post('/login', [AuthController::class, 'login']);
    Route::middleware('throttle:login')->post('/auth/login', [AuthController::class, 'login']); // compat: cliente legado
    Route::middleware('throttle:password-reset')->post('/forgot-password', [PasswordResetController::class, 'sendResetLink']);
    Route::middleware('throttle:password-reset')->post('/reset-password', [PasswordResetController::class, 'reset']);

    // â?,â?,â?, Portal do Cliente (Fase 6.1) â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,
    Route::prefix('portal')->group(function () {
        Route::middleware('throttle:20,1')->post('login', [PortalAuthController::class, 'login']);

        Route::middleware(['auth:sanctum', 'portal.access'])->group(function () {
            Route::post('logout', [PortalAuthController::class, 'logout']);
            Route::get('me', [PortalAuthController::class, 'me']);

            Route::get('work-orders', [PortalController::class, 'workOrders']);
            Route::get('work-orders/{workOrder}', [PortalController::class, 'workOrderShow']);
            Route::get('quotes', [PortalController::class, 'quotes']);
            Route::match(['post', 'put'], 'quotes/{id}/status', [PortalController::class, 'updateQuoteStatus']);
            Route::get('financials', [PortalController::class, 'financials']);
            Route::get('certificates', [PortalController::class, 'certificates']);
            Route::get('equipment', [PortalController::class, 'equipment']);
            Route::post('service-calls', [PortalController::class, 'newServiceCall']);
            Route::get('work-orders/{workOrder}/photos', [PortalController::class, 'workOrderPhotos']);
            Route::post('work-orders/{workOrder}/signature', [PortalController::class, 'submitSignature']);

            // Portal Tickets
            Route::get('tickets', [PortalTicketController::class, 'index']);
            Route::post('tickets', [PortalTicketController::class, 'store']);
            Route::get('tickets/{id}', [PortalTicketController::class, 'show']);
            Route::put('tickets/{id}', [PortalTicketController::class, 'update']);
            Route::post('tickets/{id}/messages', [PortalTicketController::class, 'addMessage']);
        });
    });

    // --- Webhooks (público, sem autenticação Sanctum, com verificação de assinatura) ---
    Route::prefix('webhooks')->middleware(['verify.webhook', 'throttle:120,1'])->group(function () {
        Route::post('whatsapp/status', [WhatsAppWebhookController::class, 'handleStatus']);
        Route::post('whatsapp/messages', [WhatsAppWebhookController::class, 'handleMessage']);
    });

    // --- Rotas autenticadas ---
    Route::middleware(['auth:sanctum', 'check.tenant'])->group(function () {
        // As rotas modulares agora são carregadas no bootstrap/app.php via "then"
    }); // end auth:sanctum + check.tenant

});

Route::prefix('v1')->group(function () {
    // â”€â”€â”€ PUBLIC: Work Order Rating â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::middleware('throttle:30,1')
        ->post('rate/{token}', [WorkOrderRatingController::class, 'submitRating'])
        ->name('api.v1.public.work-order-rating');

    // Webhooks (verificação por assinatura)
    Route::prefix('webhooks')->middleware(['verify.webhook', 'throttle:webhooks'])->group(function () {
        Route::post('whatsapp', [WhatsAppWebhookController::class, 'handle'])
            ->name('api.v1.public.webhooks.whatsapp');
        Route::post('email', [CrmMessageController::class, 'webhookEmail'])
            ->name('api.v1.public.webhooks.email');
    });

    // â?,â?,â?, Rotas públicas (sem autenticação, com token) â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,â?,
    Route::prefix('portal/guest')->group(function () {
        Route::middleware('throttle:120,1')->get('{token}', [PortalGuestController::class, 'show']);
        Route::middleware('throttle:30,1')->post('{token}/consume', [PortalGuestController::class, 'consume']);
    });

    Route::prefix('quotes')->group(function () {
        Route::middleware('throttle:120,1')->get('{quote}/public-view', [QuoteController::class, 'publicView'])
            ->name('api.v1.public.quotes.public-view');
        Route::middleware('throttle:120,1')->get('{quote}/public-pdf', [QuoteController::class, 'publicPdf'])
            ->name('api.v1.public.quotes.public-pdf');
        Route::middleware('throttle:30,1')->post('{quote}/public-approve', [QuoteController::class, 'publicApprove'])
            ->name('api.v1.public.quotes.public-approve');

        // 4.33 Magic Token Approval
        Route::middleware('throttle:120,1')->get('proposal/{magicToken}', [QuotePublicApprovalController::class, 'show'])
            ->name('api.v1.public.quotes.proposal.show');
        Route::middleware('throttle:30,1')->post('proposal/{magicToken}/approve', [QuotePublicApprovalController::class, 'approve'])
            ->name('api.v1.public.quotes.proposal.approve');
        Route::middleware('throttle:30,1')->post('proposal/{magicToken}/reject', [QuotePublicApprovalController::class, 'reject'])
            ->name('api.v1.public.quotes.proposal.reject');
    });

    // Rota pública: Assinatura digital de orçamento (#24)
    Route::middleware('throttle:30,1')->post('crm/quotes/sign/{token}', [CrmAdvancedController::class, 'signQuote']);

    // Rota pública: Verificação de certificado (#46)
    Route::middleware('throttle:60,1')->get('verify-certificate/{code}', [MetrologyQualityController::class, 'verifyCertificate']);

    // QR Code público — status de calibração do equipamento
    Route::middleware('throttle:120,1')->get('equipment-qr/{token}', [WorkOrderFieldController::class, 'equipmentByQr']);

    // Catálogo público — serviços por slug (link compartilhável)
    Route::middleware('throttle:120,1')->get('catalog/{slug}', [CatalogController::class, 'publicShow']);

    // QR Code Tracking (público — registra scan e redireciona)
    Route::middleware('throttle:120,1')->get('track/os/{workOrder}', PublicWorkOrderTrackingController::class)
        ->name('api.v1.public.track.os');

    // Email Tracking (Pixel)
    Route::middleware('throttle:600,1')->get('pixel/{trackingId}', [EmailController::class, 'track'])
        ->name('api.v1.public.pixel');

    // #19 — Consulta pública DANFE por chave de acesso
    Route::middleware('throttle:60,1')->post('fiscal/consulta-publica', [FiscalPublicController::class, 'consultaPublica']);

    // Webhook da API externa (SEFAZ assíncrona): callback quando a nota é autorizada/rejeitada
    Route::middleware(['throttle:120,1', 'verify.fiscal_webhook'])->post('fiscal/webhook', FiscalWebhookCallbackController::class);

    // Payment Gateway Webhook (Phase 6 — callback público para PIX/Boleto)
    Route::middleware('throttle:120,1')->post('webhooks/payment', [PaymentWebhookController::class, 'handle']);
});
