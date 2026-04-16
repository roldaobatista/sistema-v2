<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\GenerateLabelRequest;
use App\Http\Requests\Stock\PreviewLabelRequest;
use App\Models\Product;
use App\Services\LabelGeneratorService;
use App\Services\PdfGeneratorService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockLabelController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(
        private LabelGeneratorService $labelService,
        private PdfGeneratorService $pdfService,
    ) {}

    public function formats(): JsonResponse
    {
        $formats = $this->labelService->getFormats();
        $list = [];
        foreach ($formats as $key => $config) {
            $list[] = [
                'key' => $key,
                'name' => $config['name'],
                'width_mm' => $config['width_mm'],
                'height_mm' => $config['height_mm'],
                'output' => $config['output'],
            ];
        }

        return ApiResponse::data($list);
    }

    public function preview(PreviewLabelRequest $request): Response|JsonResponse|BinaryFileResponse
    {
        $validated = $request->validated();

        try {
            $tenantId = $this->resolvedTenantId();
            $product = Product::where('tenant_id', $tenantId)->find($validated['product_id']);
            if (! $product) {
                return ApiResponse::message('Produto não encontrado.', 404);
            }
            if (! $product->is_active) {
                return ApiResponse::message('Produto inativo não pode gerar etiqueta.', 422);
            }

            $formats = $this->labelService->getFormats();
            if (! isset($formats[$validated['format_key']])) {
                return ApiResponse::message('Formato de etiqueta inválido.', 422);
            }

            $showLogo = filter_var($validated['show_logo'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $companyLogoPath = $showLogo ? $this->pdfService->getCompanyLogoPath($tenantId) : null;

            $expanded = new Collection([$product]);
            $path = $this->labelService->generatePdf($expanded, $validated['format_key'], $companyLogoPath);

            return response()->file($path, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="etiqueta-preview.pdf"',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Label preview failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar preview da etiqueta.', 500);
        }
    }

    public function generate(GenerateLabelRequest $request): Response|JsonResponse|BinaryFileResponse
    {
        $validated = $request->validated();

        try {
            $formatKey = $validated['format_key'];
            $formats = $this->labelService->getFormats();
            if (! isset($formats[$formatKey])) {
                return ApiResponse::message('Formato de etiqueta inválido.', 422);
            }

            $tenantId = $this->resolvedTenantId();

            if (! empty($validated['items'])) {
                $expanded = new Collection;
                foreach ($validated['items'] as $row) {
                    $p = Product::where('tenant_id', $tenantId)->find($row['product_id']);
                    if (! $p) {
                        continue;
                    }
                    if (! $p->is_active) {
                        return ApiResponse::message('Produto inativo não pode gerar etiqueta: '.$p->name, 422);
                    }
                    $qty = (int) ($row['quantity'] ?? 1);
                    for ($i = 0; $i < $qty; $i++) {
                        $expanded->push($p);
                    }
                }
            } else {
                $productIds = $validated['product_ids'] ?? [];
                $quantity = (int) ($validated['quantity'] ?? 1);
                $products = Product::where('tenant_id', $tenantId)->whereIn('id', $productIds)->get();
                foreach ($products as $p) {
                    if (! $p->is_active) {
                        return ApiResponse::message('Produto inativo não pode gerar etiqueta: '.$p->name, 422);
                    }
                }
                $expanded = new Collection;
                foreach ($products as $p) {
                    for ($i = 0; $i < $quantity; $i++) {
                        $expanded->push($p);
                    }
                }
            }

            if ($expanded->isEmpty()) {
                return ApiResponse::message('Nenhum produto encontrado.', 404);
            }

            $format = $formats[$formatKey];
            $output = $format['output'] ?? 'pdf';

            if ($output === 'zpl') {
                $zpl = $this->labelService->generateZplMultiple($expanded, $formatKey);

                return response($zpl, 200, [
                    'Content-Type' => 'text/plain; charset=UTF-8',
                    'Content-Disposition' => 'attachment; filename="etiquetas.zpl"',
                ]);
            }

            $showLogo = filter_var($validated['show_logo'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $companyLogoPath = $showLogo ? $this->pdfService->getCompanyLogoPath($tenantId) : null;
            $path = $this->labelService->generatePdf($expanded, $formatKey, $companyLogoPath);

            return response()->file($path, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="etiquetas-estoque.pdf"',
            ])->deleteFileAfterSend(true);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Label generation failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar etiquetas.', 500);
        }
    }
}
