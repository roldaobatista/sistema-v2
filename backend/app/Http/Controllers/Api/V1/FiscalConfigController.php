<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\UpdateFiscalConfigRequest;
use App\Http\Requests\Fiscal\UploadCertificateRequest;
use App\Models\Tenant;
use App\Services\Fiscal\CertificateService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FiscalConfigController extends Controller
{
    use ResolvesCurrentTenant;

    private const FISCAL_FIELDS = [
        'fiscal_regime',
        'cnae_code',
        'state_registration',
        'city_registration',
        'fiscal_nfse_token',
        'fiscal_nfse_city',
        'fiscal_nfe_series',
        'fiscal_nfe_next_number',
        'fiscal_nfse_rps_series',
        'fiscal_nfse_rps_next_number',
        'fiscal_environment',
    ];

    private const FISCAL_REGIMES = [
        1 => 'Simples Nacional',
        2 => 'Lucro Presumido',
        3 => 'Lucro Real',
        4 => 'MEI',
    ];

    private const NFSE_CITIES = [
        'rondonopolis' => 'Rondonópolis/MT (ABRASF 2.03)',
        'campo_grande' => 'Campo Grande/MS (DSF)',
    ];

    private const ISS_EXIGIBILIDADE = [
        ['code' => '1', 'description' => 'Exigível'],
        ['code' => '2', 'description' => 'Não incidência'],
        ['code' => '3', 'description' => 'Isenção'],
        ['code' => '4', 'description' => 'Exportação'],
        ['code' => '5', 'description' => 'Imunidade'],
        ['code' => '6', 'description' => 'Exigibilidade suspensa por decisão judicial'],
        ['code' => '7', 'description' => 'Exigibilidade suspensa por processo administrativo'],
    ];

    /**
     * GET /fiscal/config
     */
    public function show(Request $request): JsonResponse
    {
        $tenant = Tenant::find($this->tenantId());

        if (! $tenant) {
            return ApiResponse::message('Tenant não encontrado.', 404);
        }

        $config = [];
        foreach (self::FISCAL_FIELDS as $field) {
            $config[$field] = $tenant->{$field};
        }

        $config['has_certificate'] = ! empty($tenant->fiscal_certificate_path);
        $config['certificate_expires_at'] = $tenant->fiscal_certificate_expires_at?->format('Y-m-d');

        return ApiResponse::data($config, 200, [
            'meta' => [
                'fiscal_regimes' => self::FISCAL_REGIMES,
                'nfse_cities' => self::NFSE_CITIES,
                'environments' => [
                    'homologation' => 'Homologação (testes)',
                    'production' => 'Produção',
                ],
            ],
        ]);
    }

    /**
     * PUT /fiscal/config
     */
    public function update(UpdateFiscalConfigRequest $request): JsonResponse
    {
        $tenant = Tenant::find($this->tenantId());

        if (! $tenant) {
            return ApiResponse::message('Tenant não encontrado.', 404);
        }
        $validated = $request->validated();
        $tenant->update($validated);

        return ApiResponse::data($tenant->only(self::FISCAL_FIELDS), 200, ['message' => 'Configuração fiscal atualizada com sucesso.']);
    }

    /**
     * POST /fiscal/config/certificate
     */
    public function uploadCertificate(UploadCertificateRequest $request, CertificateService $service): JsonResponse
    {
        $tenant = Tenant::find($this->tenantId());

        if (! $tenant) {
            return ApiResponse::message('Tenant não encontrado.', 404);
        }
        $request->validated();
        $result = $service->upload(
            $tenant,
            $request->file('certificate'),
            $request->input('password')
        );

        return ApiResponse::data($result, $result['success'] ? 200 : 422);
    }

    /**
     * GET /fiscal/config/certificate/status
     */
    public function certificateStatus(Request $request, CertificateService $service): JsonResponse
    {
        $tenant = Tenant::find($this->tenantId());

        if (! $tenant) {
            return ApiResponse::message('Tenant não encontrado.', 404);
        }

        return ApiResponse::data($service->status($tenant));
    }

    /**
     * DELETE /fiscal/config/certificate
     */
    public function removeCertificate(Request $request, CertificateService $service): JsonResponse
    {
        $tenant = Tenant::find($this->tenantId());

        if (! $tenant) {
            return ApiResponse::message('Tenant não encontrado.', 404);
        }

        $service->remove($tenant);

        return ApiResponse::message('Certificado removido com sucesso.');
    }

    /**
     * GET /fiscal/config/cfop-options
     */
    public function cfopOptions(): JsonResponse
    {
        return ApiResponse::data(self::CFOP_CODES);
    }

    /**
     * GET /fiscal/config/csosn-options
     */
    public function csosnOptions(): JsonResponse
    {
        return ApiResponse::data(self::CSOSN_CODES);
    }

    // Common CFOP codes for service/product companies
    private const CFOP_CODES = [
        // Saídas dentro do estado
        ['code' => '5101', 'description' => 'Venda de produção - operação interna'],
        ['code' => '5102', 'description' => 'Venda de mercadoria adquirida - operação interna'],
        ['code' => '5405', 'description' => 'Venda de mercadoria adquirida com substituição tributária - interna'],
        ['code' => '5933', 'description' => 'Prestação de serviço tributada pelo ISSQN'],
        // Saídas fora do estado
        ['code' => '6101', 'description' => 'Venda de produção - operação interestadual'],
        ['code' => '6102', 'description' => 'Venda de mercadoria adquirida - operação interestadual'],
        ['code' => '6108', 'description' => 'Venda de mercadoria adquirida a não contribuinte - interestadual'],
        // Devoluções
        ['code' => '5201', 'description' => 'Devolução de compra - operação interna'],
        ['code' => '5202', 'description' => 'Devolução de compra - revenda - interna'],
        ['code' => '6201', 'description' => 'Devolução de compra - operação interestadual'],
        // Remessa/Retorno
        ['code' => '5908', 'description' => 'Remessa de bem por conta de contrato de comodato'],
        ['code' => '5915', 'description' => 'Remessa de mercadoria para conserto/reparo'],
        ['code' => '5916', 'description' => 'Retorno de mercadoria de conserto/reparo'],
    ];

    // CSOSN codes for Simples Nacional
    private const CSOSN_CODES = [
        ['code' => '102', 'description' => 'Tributada pelo Simples Nacional sem permissão de crédito'],
        ['code' => '103', 'description' => 'Isenção do ICMS no Simples Nacional - faixa de receita bruta'],
        ['code' => '300', 'description' => 'Imune (art. 150, VI, CF)'],
        ['code' => '400', 'description' => 'Não tributada pelo Simples Nacional'],
        ['code' => '500', 'description' => 'ICMS cobrado anteriormente por substituição tributária ou antecipação'],
        ['code' => '900', 'description' => 'Outros (regime cumulativo, etc.)'],
    ];

    // Common LC 116 service codes
    private const LC116_COMMON = [
        ['code' => '1.07', 'description' => 'Suporte técnico em informática, hardware e software'],
        ['code' => '1.08', 'description' => 'Planejamento, confecção, manutenção e atualização de páginas web'],
        ['code' => '7.01', 'description' => 'Engenharia, agronomia, agrimensura, arquitetura, geologia'],
        ['code' => '14.01', 'description' => 'Lubrificação, limpeza, lustração, revisão de máquinas e veículos'],
        ['code' => '14.02', 'description' => 'Assistência técnica'],
        ['code' => '14.03', 'description' => 'Recondicionamento de motores'],
        ['code' => '14.06', 'description' => 'Instalação e montagem de aparelhos, máquinas e equipamentos'],
        ['code' => '14.09', 'description' => 'Alfaiataria e costura em geral'],
        ['code' => '14.13', 'description' => 'Carpintaria e serralheria'],
        ['code' => '14.14', 'description' => 'Guincho intramunicipal, guindaste e içamento'],
        ['code' => '17.01', 'description' => 'Assessoria ou consultoria de qualquer natureza'],
        ['code' => '17.02', 'description' => 'Datilografia, digitação, estenografia, expediente e secretaria'],
        ['code' => '25.01', 'description' => 'Funerais, inclusive fornecimento de caixão'],
        ['code' => '25.03', 'description' => 'Translado intramunicipal e cremação de corpos e partes de corpos cadavéricos'],
    ];

    /**
     * GET /fiscal/config/iss-exigibilidade-options
     */
    public function issExigibilidadeOptions(): JsonResponse
    {
        return ApiResponse::data(self::ISS_EXIGIBILIDADE);
    }

    /**
     * GET /fiscal/config/lc116-options
     */
    public function lc116Options(): JsonResponse
    {
        return ApiResponse::data(self::LC116_COMMON);
    }
}
