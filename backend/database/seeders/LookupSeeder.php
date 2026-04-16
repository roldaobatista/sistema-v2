<?php

namespace Database\Seeders;

use App\Models\Lookups\AccountReceivableCategory;
use App\Models\Lookups\AutomationReportFormat;
use App\Models\Lookups\AutomationReportFrequency;
use App\Models\Lookups\AutomationReportType;
use App\Models\Lookups\BankAccountType;
use App\Models\Lookups\CalibrationType;
use App\Models\Lookups\CancellationReason;
use App\Models\Lookups\ContractType;
use App\Models\Lookups\CustomerCompanySize;
use App\Models\Lookups\CustomerRating;
use App\Models\Lookups\CustomerSegment;
use App\Models\Lookups\DocumentType;
use App\Models\Lookups\EquipmentBrand;
use App\Models\Lookups\EquipmentCategory;
use App\Models\Lookups\EquipmentType;
use App\Models\Lookups\FleetFuelType;
use App\Models\Lookups\FleetVehicleStatus;
use App\Models\Lookups\FleetVehicleType;
use App\Models\Lookups\FollowUpChannel;
use App\Models\Lookups\FollowUpStatus;
use App\Models\Lookups\FuelingFuelType;
use App\Models\Lookups\InmetroSealStatus;
use App\Models\Lookups\InmetroSealType;
use App\Models\Lookups\LeadSource;
use App\Models\Lookups\MaintenanceType;
use App\Models\Lookups\MeasurementUnit;
use App\Models\Lookups\OnboardingTemplateType;
use App\Models\Lookups\PaymentTerm;
use App\Models\Lookups\PriceTableAdjustmentType;
use App\Models\Lookups\QuoteSource;
use App\Models\Lookups\ServiceType;
use App\Models\Lookups\SupplierContractPaymentFrequency;
use App\Models\Lookups\TvCameraType;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LookupSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::pluck('id');

        foreach ($tenants as $tenantId) {
            $this->seedForTenant($tenantId);
        }
    }

    private function seedForTenant(int $tenantId): void
    {
        $this->seedLookup(EquipmentCategory::class, $tenantId, [
            'Balancas Comerciais',
            'Balancas Industriais',
            'Balancas Rodoviarias',
            'Balancas Analiticas e de Precisao',
            'Sistemas de Dosagem',
            'Etiquetadoras e Impressoras',
            'Acessorios de Pesagem',
            'Automacao Integrada',
        ]);

        $this->seedLookup(EquipmentType::class, $tenantId, [
            'Balanca de Piso',
            'Balanca Rodoviaria',
            'Balanca Analitica',
            'Balanca de Bancada',
            'Balanca Comercial',
            'Balanca de Precisao',
            'Balanca de Contagem',
            'Balanca Suspensa',
            'Balanca de Plataforma',
            'Balanca para Gado',
            'Indicador de Peso',
            'Celula de Carga',
            'Checkweigher',
            'Dosadora',
            'Impressora',
            'Leitor de Codigo de Barras',
            'Terminal de Pesagem',
            'Controlador de Processo',
            'Termometro',
            'Umidimetro',
        ]);

        $this->seedLookup(EquipmentBrand::class, $tenantId, [
            'Toledo',
            'Marte',
            'Filizola',
            'Shimadzu',
            'Lider',
            'Coimma',
            'Rice Lake',
            'Alfa Instrumentos',
            'Digitron',
            'Balmak',
            'Urano',
            'Welmy',
            'Ramuza',
            'Micheletti',
            'MK Automacao',
            'Brapenta',
            'Saturno',
            'Avery Weigh-Tronix',
            'Schenck',
            'Mettler Toledo',
            'Baxtran',
            'Luca',
            'Sweda',
        ]);

        $this->seedLookup(ServiceType::class, $tenantId, [
            'Diagnostico',
            'Manutencao Corretiva',
            'Preventiva',
            'Calibracao',
            'Instalacao',
            'Retorno',
            'Garantia',
            'Vistoria',
            'Consultoria',
            'Treinamento',
            'Comissionamento',
            'Auditoria Metrologica',
            'Afericao',
            'Inspecao de Seguranca',
            'Retrofit',
            'Parametrizacao',
        ]);

        $this->seedLookup(LeadSource::class, $tenantId, [
            'Indicacao de Cliente',
            'Google Organico',
            'Google Ads',
            'Instagram',
            'LinkedIn',
            'WhatsApp',
            'Feira e Evento',
            'Representante Comercial',
            'Site Institucional',
            'Marketplace',
            'Prospeccao Ativa',
            'Carteira Existente',
            'Canal Parceiro',
            'Distribuidor',
            'Licitacao',
            'Outro',
        ]);

        $this->seedLookup(CustomerSegment::class, $tenantId, [
            'Supermercado',
            'Atacarejo',
            'Padaria',
            'Acougue',
            'Farmacia',
            'Industria Alimenticia',
            'Industria Quimica',
            'Logistica e Transporte',
            'Agronegocio',
            'Frigorifico',
            'Laboratorio',
            'Hospital',
            'Clinica',
            'Varejo em Geral',
            'Centro de Distribuicao',
            'Restaurante',
            'Panificacao Industrial',
            'Cooperativa',
            'Porto Seco',
            'Outro',
        ]);

        $this->seedLookup(CustomerCompanySize::class, $tenantId, [
            ['name' => 'MEI', 'slug' => 'mei'],
            ['name' => 'Microempresa', 'slug' => 'micro'],
            ['name' => 'Pequena Empresa', 'slug' => 'pequena'],
            ['name' => 'Media Empresa', 'slug' => 'media'],
            ['name' => 'Grande Empresa', 'slug' => 'grande'],
            ['name' => 'Enterprise', 'slug' => 'enterprise'],
            ['name' => 'Grupo Economico', 'slug' => 'grupo-economico'],
            ['name' => 'Publico', 'slug' => 'publico'],
        ]);

        $this->seedLookup(CustomerRating::class, $tenantId, [
            ['name' => 'A - Alto Potencial', 'slug' => 'A'],
            ['name' => 'B - Medio Potencial', 'slug' => 'B'],
            ['name' => 'C - Baixo Potencial', 'slug' => 'C'],
            ['name' => 'D - Inativo', 'slug' => 'D'],
            ['name' => 'AA - Estrategico', 'slug' => 'AA'],
            ['name' => 'E - Recuperacao', 'slug' => 'E'],
        ]);

        $this->seedLookup(ContractType::class, $tenantId, [
            ['name' => 'Avulso', 'slug' => 'avulso'],
            ['name' => 'Contrato Mensal', 'slug' => 'contrato_mensal'],
            ['name' => 'Contrato Anual', 'slug' => 'contrato_anual'],
            ['name' => 'Contrato de Manutencao Preventiva', 'slug' => 'manutencao_preventiva'],
            ['name' => 'Contrato Full Service', 'slug' => 'full_service'],
            ['name' => 'Contrato SLA Dedicado', 'slug' => 'sla_dedicado'],
            ['name' => 'Contrato de Calibracao Programada', 'slug' => 'calibracao_programada'],
        ]);

        $this->seedLookup(MeasurementUnit::class, $tenantId, [
            ['name' => 'Unidade', 'slug' => 'un', 'abbreviation' => 'UN', 'unit_type' => 'quantidade'],
            ['name' => 'Quilograma', 'slug' => 'kg', 'abbreviation' => 'kg', 'unit_type' => 'massa'],
            ['name' => 'Grama', 'slug' => 'g', 'abbreviation' => 'g', 'unit_type' => 'massa'],
            ['name' => 'Tonelada', 'slug' => 't', 'abbreviation' => 't', 'unit_type' => 'massa'],
            ['name' => 'Metro', 'slug' => 'm', 'abbreviation' => 'm', 'unit_type' => 'comprimento'],
            ['name' => 'Centimetro', 'slug' => 'cm', 'abbreviation' => 'cm', 'unit_type' => 'comprimento'],
            ['name' => 'Milimetro', 'slug' => 'mm', 'abbreviation' => 'mm', 'unit_type' => 'comprimento'],
            ['name' => 'Litro', 'slug' => 'l', 'abbreviation' => 'L', 'unit_type' => 'volume'],
            ['name' => 'Mililitro', 'slug' => 'ml', 'abbreviation' => 'mL', 'unit_type' => 'volume'],
            ['name' => 'Hora', 'slug' => 'h', 'abbreviation' => 'h', 'unit_type' => 'tempo'],
            ['name' => 'Minuto', 'slug' => 'min', 'abbreviation' => 'min', 'unit_type' => 'tempo'],
            ['name' => 'Quilometro', 'slug' => 'km', 'abbreviation' => 'km', 'unit_type' => 'distancia'],
        ]);

        $this->seedLookup(CalibrationType::class, $tenantId, [
            'Rastreavel',
            'RBC',
            'Interna',
            'Inicial de Fabrica',
            'Pos-Manutencao',
            'Periodica',
            'Extraordinaria',
            'Comparativa',
        ]);

        $this->seedLookup(MaintenanceType::class, $tenantId, [
            'Preventiva',
            'Corretiva',
            'Preditiva',
            'Emergencial',
            'Ajuste Metrologico',
            'Troca de Componentes',
            'Atualizacao de Firmware',
            'Limpeza Tecnica',
        ]);

        $this->seedLookup(DocumentType::class, $tenantId, [
            'Certificado de Calibracao',
            'Laudo Tecnico',
            'Relatorio de Atendimento',
            'ART',
            'NFe',
            'NFS-e',
            'Contrato',
            'Checklist Assinado',
            'Foto de Evidencia',
            'Manual Tecnico',
            'Comprovante de Entrega',
            'OS Assinada',
        ]);

        $this->seedLookup(AccountReceivableCategory::class, $tenantId, [
            'Servico Tecnico',
            'Calibracao',
            'Manutencao Preventiva',
            'Manutencao Corretiva',
            'Venda de Pecas',
            'Contrato Mensal',
            'Contrato Anual',
            'Treinamento',
            'Locacao de Equipamento',
            'Consultoria',
            'Instalacao',
            'Outros',
        ]);

        $this->seedLookup(CancellationReason::class, $tenantId, [
            ['name' => 'Duplicidade de Registro', 'applies_to' => ['os', 'chamado', 'orcamento']],
            ['name' => 'Cliente desistiu', 'applies_to' => ['os', 'chamado', 'orcamento']],
            ['name' => 'Sem aprovacao comercial', 'applies_to' => ['orcamento']],
            ['name' => 'Sem disponibilidade tecnica', 'applies_to' => ['os', 'chamado']],
            ['name' => 'Endereco invalido', 'applies_to' => ['os', 'chamado']],
            ['name' => 'Fora da area de atendimento', 'applies_to' => ['os', 'chamado', 'orcamento']],
            ['name' => 'Equipamento sem condicoes de atendimento', 'applies_to' => ['os', 'chamado']],
            ['name' => 'Erro de abertura', 'applies_to' => ['os', 'chamado', 'orcamento']],
            ['name' => 'Inadimplencia', 'applies_to' => ['os', 'orcamento']],
            ['name' => 'Conversao para contrato', 'applies_to' => ['orcamento']],
            ['name' => 'Substituido por nova OS', 'applies_to' => ['os', 'chamado']],
        ]);

        $this->seedLookup(PaymentTerm::class, $tenantId, [
            'A Vista',
            '7 dias',
            '14 dias',
            '21 dias',
            '28 dias',
            '30 dias',
            '30/60 dias',
            '30/60/90 dias',
            '15/30/45 dias',
            'Entrada + 30 dias',
            'Entrada + 30/60 dias',
            'Entrada + 30/60/90 dias',
            '50% Entrada + 50% Entrega',
            'Cartao em 2x',
            'Cartao em 3x',
            'Cartao em 6x',
            'Cartao em 12x',
            'Sob Consulta',
            'Contra Entrega',
        ]);

        $this->seedLookup(QuoteSource::class, $tenantId, [
            'Prospeccao',
            'Retorno',
            'Contato Direto',
            'Indicacao',
            'Calibracao Vencendo',
            'Contrato/Renovacao',
            'Feira/Evento',
            'Google/Internet',
            'Campanha Comercial',
            'Cross-sell Carteira',
            'Porteiro Digital',
            'Parceria Tecnica',
            'Inbound Site',
        ]);

        $this->seedLookup(BankAccountType::class, $tenantId, [
            ['name' => 'Conta Corrente', 'slug' => 'corrente'],
            ['name' => 'Poupanca', 'slug' => 'poupanca'],
            ['name' => 'Conta Pagamento', 'slug' => 'pagamento'],
        ]);

        $this->seedLookup(FleetVehicleType::class, $tenantId, [
            ['name' => 'Carro', 'slug' => 'car'],
            ['name' => 'Caminhao', 'slug' => 'truck'],
            ['name' => 'Motocicleta', 'slug' => 'motorcycle'],
            ['name' => 'Van', 'slug' => 'van'],
        ]);

        $this->seedLookup(FleetFuelType::class, $tenantId, [
            ['name' => 'Flex', 'slug' => 'flex'],
            ['name' => 'Diesel', 'slug' => 'diesel'],
            ['name' => 'Gasolina', 'slug' => 'gasoline'],
            ['name' => 'Eletrico', 'slug' => 'electric'],
            ['name' => 'Etanol', 'slug' => 'ethanol'],
        ]);

        $this->seedLookup(FleetVehicleStatus::class, $tenantId, [
            ['name' => 'Ativo', 'slug' => 'active'],
            ['name' => 'Manutencao', 'slug' => 'maintenance'],
            ['name' => 'Inativo', 'slug' => 'inactive'],
        ]);

        $this->seedLookup(FuelingFuelType::class, $tenantId, [
            ['name' => 'Diesel', 'slug' => 'diesel'],
            ['name' => 'Diesel S10', 'slug' => 'diesel_s10'],
            ['name' => 'Gasolina', 'slug' => 'gasolina'],
            ['name' => 'Etanol', 'slug' => 'etanol'],
        ]);

        $this->seedLookup(InmetroSealType::class, $tenantId, [
            ['name' => 'Selo Reparo', 'slug' => 'seal_reparo'],
            ['name' => 'Lacre', 'slug' => 'seal'],
        ]);

        $this->seedLookup(InmetroSealStatus::class, $tenantId, [
            ['name' => 'Disponivel', 'slug' => 'available'],
            ['name' => 'Com Tecnico', 'slug' => 'assigned'],
            ['name' => 'Utilizado', 'slug' => 'used'],
            ['name' => 'Danificado', 'slug' => 'damaged'],
            ['name' => 'Extraviado', 'slug' => 'lost'],
        ]);

        $this->seedLookup(TvCameraType::class, $tenantId, [
            ['name' => 'IP', 'slug' => 'ip'],
            ['name' => 'USB', 'slug' => 'usb'],
            ['name' => 'Analogica', 'slug' => 'analog'],
            ['name' => 'Wi-Fi', 'slug' => 'wifi'],
        ]);

        $this->seedLookup(OnboardingTemplateType::class, $tenantId, [
            ['name' => 'Admissao', 'slug' => 'admission'],
            ['name' => 'Desligamento', 'slug' => 'dismissal'],
        ]);

        $this->seedLookup(FollowUpChannel::class, $tenantId, [
            ['name' => 'Telefone', 'slug' => 'phone'],
            ['name' => 'WhatsApp', 'slug' => 'whatsapp'],
            ['name' => 'E-mail', 'slug' => 'email'],
            ['name' => 'Visita', 'slug' => 'visit'],
        ]);

        $this->seedLookup(FollowUpStatus::class, $tenantId, [
            ['name' => 'Pendente', 'slug' => 'pending'],
            ['name' => 'Concluido', 'slug' => 'completed'],
            ['name' => 'Atrasado', 'slug' => 'overdue'],
        ]);

        $this->seedLookup(PriceTableAdjustmentType::class, $tenantId, [
            ['name' => 'Markup', 'slug' => 'markup'],
            ['name' => 'Desconto', 'slug' => 'discount'],
        ]);

        $this->seedLookup(AutomationReportType::class, $tenantId, [
            ['name' => 'Ordens de Servico', 'slug' => 'work-orders'],
            ['name' => 'Produtividade', 'slug' => 'productivity'],
            ['name' => 'Financeiro', 'slug' => 'financial'],
            ['name' => 'Comissoes', 'slug' => 'commissions'],
            ['name' => 'Lucratividade', 'slug' => 'profitability'],
            ['name' => 'Orcamentos', 'slug' => 'quotes'],
            ['name' => 'Chamados', 'slug' => 'service-calls'],
            ['name' => 'Caixa Tecnico', 'slug' => 'technician-cash'],
            ['name' => 'CRM', 'slug' => 'crm'],
            ['name' => 'Equipamentos', 'slug' => 'equipments'],
            ['name' => 'Fornecedores', 'slug' => 'suppliers'],
            ['name' => 'Estoque', 'slug' => 'stock'],
            ['name' => 'Clientes', 'slug' => 'customers'],
        ]);

        $this->seedLookup(AutomationReportFrequency::class, $tenantId, [
            ['name' => 'Diario', 'slug' => 'daily'],
            ['name' => 'Semanal', 'slug' => 'weekly'],
            ['name' => 'Mensal', 'slug' => 'monthly'],
        ]);

        $this->seedLookup(AutomationReportFormat::class, $tenantId, [
            ['name' => 'PDF', 'slug' => 'pdf'],
            ['name' => 'Excel', 'slug' => 'excel'],
        ]);

        $this->seedLookup(SupplierContractPaymentFrequency::class, $tenantId, [
            ['name' => 'Mensal', 'slug' => 'monthly'],
            ['name' => 'Trimestral', 'slug' => 'quarterly'],
            ['name' => 'Anual', 'slug' => 'annual'],
            ['name' => 'Unico', 'slug' => 'one_time'],
        ]);
    }

    private function seedLookup(string $modelClass, int $tenantId, array $items): void
    {
        $tableName = (new $modelClass)->getTable();
        if (! Schema::hasTable($tableName)) {
            return;
        }

        foreach ($items as $i => $item) {
            $name = is_array($item) ? (string) ($item['name'] ?? '') : (string) $item;
            if ($name === '') {
                continue;
            }

            $slug = is_array($item) && ! empty($item['slug'])
                ? (string) $item['slug']
                : Str::slug($name);

            if ($slug === '') {
                continue;
            }

            $payload = [
                'name' => $name,
                'sort_order' => is_array($item) && isset($item['sort_order']) ? (int) $item['sort_order'] : $i,
                'is_active' => is_array($item) && array_key_exists('is_active', $item) ? (bool) $item['is_active'] : true,
            ];

            if (is_array($item) && array_key_exists('description', $item)) {
                $payload['description'] = $item['description'];
            }

            if (is_array($item) && array_key_exists('color', $item)) {
                $payload['color'] = $item['color'];
            }

            if (is_array($item) && array_key_exists('icon', $item)) {
                $payload['icon'] = $item['icon'];
            }

            if (is_array($item) && array_key_exists('abbreviation', $item)) {
                $payload['abbreviation'] = $item['abbreviation'];
            }

            if (is_array($item) && array_key_exists('unit_type', $item)) {
                $payload['unit_type'] = $item['unit_type'];
            }

            if (is_array($item) && array_key_exists('applies_to', $item)) {
                $payload['applies_to'] = $item['applies_to'];
            }

            $modelClass::firstOrCreate(
                ['tenant_id' => $tenantId, 'slug' => $slug],
                $payload
            );
        }
    }
}
