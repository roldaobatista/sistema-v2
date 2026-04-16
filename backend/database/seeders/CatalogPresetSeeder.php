<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class CatalogPresetSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('services')) {
            $this->command->warn('Tabelas de catalogo nao encontradas. Seeder ignorado.');

            return;
        }

        $productCategories = [
            'Pecas de Reposicao',
            'Eletronica de Pesagem',
            'Materiais de Instalacao',
            'Consumo Tecnico',
            'Acessorios de Balanca',
        ];

        $serviceCategories = [
            'Calibracao',
            'Manutencao Preventiva',
            'Manutencao Corretiva',
            'Instalacao',
            'Visita Tecnica',
            'Consultoria',
        ];

        $products = [
            ['code' => 'PRD-CEL-300', 'name' => 'Celula de Carga 300kg', 'category' => 'Eletronica de Pesagem', 'cost_price' => 210, 'sell_price' => 320, 'stock_qty' => 22, 'stock_min' => 4],
            ['code' => 'PRD-CEL-1000', 'name' => 'Celula de Carga 1000kg', 'category' => 'Eletronica de Pesagem', 'cost_price' => 360, 'sell_price' => 520, 'stock_qty' => 15, 'stock_min' => 3],
            ['code' => 'PRD-CEL-5000', 'name' => 'Celula de Carga 5000kg', 'category' => 'Eletronica de Pesagem', 'cost_price' => 780, 'sell_price' => 1120, 'stock_qty' => 8, 'stock_min' => 2],
            ['code' => 'PRD-CAB-5M', 'name' => 'Cabo Blindado 5m', 'category' => 'Materiais de Instalacao', 'cost_price' => 28, 'sell_price' => 55, 'stock_qty' => 40, 'stock_min' => 10],
            ['code' => 'PRD-CAB-10M', 'name' => 'Cabo Blindado 10m', 'category' => 'Materiais de Instalacao', 'cost_price' => 44, 'sell_price' => 89, 'stock_qty' => 30, 'stock_min' => 8],
            ['code' => 'PRD-CONECTOR-IP67', 'name' => 'Conector IP67', 'category' => 'Materiais de Instalacao', 'cost_price' => 9, 'sell_price' => 22, 'stock_qty' => 120, 'stock_min' => 25],
            ['code' => 'PRD-FONTE-12V', 'name' => 'Fonte 12V 5A', 'category' => 'Eletronica de Pesagem', 'cost_price' => 42, 'sell_price' => 95, 'stock_qty' => 28, 'stock_min' => 6],
            ['code' => 'PRD-FONTE-24V', 'name' => 'Fonte 24V 3A', 'category' => 'Eletronica de Pesagem', 'cost_price' => 58, 'sell_price' => 120, 'stock_qty' => 18, 'stock_min' => 5],
            ['code' => 'PRD-IND-UNIV', 'name' => 'Indicador Digital Universal', 'category' => 'Acessorios de Balanca', 'cost_price' => 820, 'sell_price' => 1290, 'stock_qty' => 9, 'stock_min' => 2],
            ['code' => 'PRD-DISPLAY-LED', 'name' => 'Display LED Externo', 'category' => 'Acessorios de Balanca', 'cost_price' => 260, 'sell_price' => 450, 'stock_qty' => 14, 'stock_min' => 3],
            ['code' => 'PRD-TECLADO-MEM', 'name' => 'Teclado Membrana 30 teclas', 'category' => 'Pecas de Reposicao', 'cost_price' => 65, 'sell_price' => 140, 'stock_qty' => 34, 'stock_min' => 8],
            ['code' => 'PRD-BATERIA-7AH', 'name' => 'Bateria Selada 12V 7Ah', 'category' => 'Pecas de Reposicao', 'cost_price' => 75, 'sell_price' => 155, 'stock_qty' => 26, 'stock_min' => 6],
            ['code' => 'PRD-PLACA-MAE-A1', 'name' => 'Placa Mae Controladora A1', 'category' => 'Pecas de Reposicao', 'cost_price' => 420, 'sell_price' => 690, 'stock_qty' => 10, 'stock_min' => 2],
            ['code' => 'PRD-SUPORTE-INOX', 'name' => 'Suporte Inox para Plataforma', 'category' => 'Materiais de Instalacao', 'cost_price' => 85, 'sell_price' => 170, 'stock_qty' => 20, 'stock_min' => 4],
            ['code' => 'PRD-PARAFUSO-KIT', 'name' => 'Kit Parafusos e Buchas', 'category' => 'Consumo Tecnico', 'cost_price' => 12, 'sell_price' => 29, 'stock_qty' => 200, 'stock_min' => 40],
            ['code' => 'PRD-LACRE-SEG', 'name' => 'Lacre de Seguranca', 'category' => 'Consumo Tecnico', 'cost_price' => 2, 'sell_price' => 6, 'stock_qty' => 800, 'stock_min' => 150],
            ['code' => 'PRD-ETIQ-TER-40', 'name' => 'Etiqueta Termica 40x40', 'category' => 'Consumo Tecnico', 'cost_price' => 11, 'sell_price' => 25, 'stock_qty' => 90, 'stock_min' => 20],
            ['code' => 'PRD-BOBINA-80', 'name' => 'Bobina Termica 80mm', 'category' => 'Consumo Tecnico', 'cost_price' => 7, 'sell_price' => 17, 'stock_qty' => 120, 'stock_min' => 25],
            ['code' => 'PRD-ALCOOL-ISO', 'name' => 'Alcool Isopropilico 500ml', 'category' => 'Consumo Tecnico', 'cost_price' => 14, 'sell_price' => 32, 'stock_qty' => 55, 'stock_min' => 10],
            ['code' => 'PRD-LIMP-CONTATO', 'name' => 'Spray Limpa Contato', 'category' => 'Consumo Tecnico', 'cost_price' => 19, 'sell_price' => 42, 'stock_qty' => 44, 'stock_min' => 8],
        ];

        $services = [
            ['code' => 'SRV-CAL-RBC-ANL', 'name' => 'Calibracao RBC Balanca Analitica', 'category' => 'Calibracao', 'default_price' => 290, 'estimated_minutes' => 70],
            ['code' => 'SRV-CAL-RAS-30', 'name' => 'Calibracao Rastreavel ate 30kg', 'category' => 'Calibracao', 'default_price' => 110, 'estimated_minutes' => 40],
            ['code' => 'SRV-CAL-RAS-300', 'name' => 'Calibracao Rastreavel ate 300kg', 'category' => 'Calibracao', 'default_price' => 220, 'estimated_minutes' => 70],
            ['code' => 'SRV-CAL-ROD-80T', 'name' => 'Calibracao Balanca Rodoviaria 80t', 'category' => 'Calibracao', 'default_price' => 1450, 'estimated_minutes' => 240],
            ['code' => 'SRV-MP-LIMPEZA', 'name' => 'Preventiva com Limpeza Geral', 'category' => 'Manutencao Preventiva', 'default_price' => 190, 'estimated_minutes' => 75],
            ['code' => 'SRV-MP-REAP', 'name' => 'Preventiva com Reaperto e Ajustes', 'category' => 'Manutencao Preventiva', 'default_price' => 240, 'estimated_minutes' => 90],
            ['code' => 'SRV-MP-CONTRATO', 'name' => 'Visita Preventiva de Contrato', 'category' => 'Manutencao Preventiva', 'default_price' => 320, 'estimated_minutes' => 110],
            ['code' => 'SRV-MC-DIAG', 'name' => 'Diagnostico Eletronico em Campo', 'category' => 'Manutencao Corretiva', 'default_price' => 180, 'estimated_minutes' => 60],
            ['code' => 'SRV-MC-TROCA-CEL', 'name' => 'Troca de Celula de Carga', 'category' => 'Manutencao Corretiva', 'default_price' => 280, 'estimated_minutes' => 95],
            ['code' => 'SRV-MC-TROCA-PLACA', 'name' => 'Troca de Placa Controladora', 'category' => 'Manutencao Corretiva', 'default_price' => 340, 'estimated_minutes' => 120],
            ['code' => 'SRV-MC-TROCA-TECLADO', 'name' => 'Troca de Teclado Membrana', 'category' => 'Manutencao Corretiva', 'default_price' => 150, 'estimated_minutes' => 50],
            ['code' => 'SRV-MC-CONF-IND', 'name' => 'Configuracao de Indicador', 'category' => 'Manutencao Corretiva', 'default_price' => 210, 'estimated_minutes' => 70],
            ['code' => 'SRV-INST-COMERCIAL', 'name' => 'Instalacao de Balanca Comercial', 'category' => 'Instalacao', 'default_price' => 260, 'estimated_minutes' => 90],
            ['code' => 'SRV-INST-INDUSTRIAL', 'name' => 'Instalacao de Balanca Industrial', 'category' => 'Instalacao', 'default_price' => 690, 'estimated_minutes' => 180],
            ['code' => 'SRV-INST-ROD', 'name' => 'Instalacao de Sistema Rodoviario', 'category' => 'Instalacao', 'default_price' => 5200, 'estimated_minutes' => 960],
            ['code' => 'SRV-VISITA-HORA', 'name' => 'Hora Tecnica em Campo', 'category' => 'Visita Tecnica', 'default_price' => 185, 'estimated_minutes' => 60],
            ['code' => 'SRV-DESLOC-KM', 'name' => 'Deslocamento por KM', 'category' => 'Visita Tecnica', 'default_price' => 1.90, 'estimated_minutes' => 0],
            ['code' => 'SRV-LAUDO-COND', 'name' => 'Laudo Tecnico de Condenacao', 'category' => 'Consultoria', 'default_price' => 220, 'estimated_minutes' => 80],
            ['code' => 'SRV-CONS-AUDITORIA', 'name' => 'Auditoria de Sistema de Pesagem', 'category' => 'Consultoria', 'default_price' => 980, 'estimated_minutes' => 240],
            ['code' => 'SRV-CONS-TREINAMENTO', 'name' => 'Treinamento de Operadores (4h)', 'category' => 'Consultoria', 'default_price' => 750, 'estimated_minutes' => 240],
        ];

        $tenants = Tenant::query()->select('id')->get();

        foreach ($tenants as $tenant) {
            $productCategoryIds = [];
            foreach ($productCategories as $categoryName) {
                $category = ProductCategory::withoutGlobalScopes()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $categoryName],
                    ['is_active' => true]
                );
                $productCategoryIds[$categoryName] = $category->id;
            }

            $serviceCategoryIds = [];
            foreach ($serviceCategories as $categoryName) {
                $category = ServiceCategory::withoutGlobalScopes()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $categoryName],
                    ['is_active' => true]
                );
                $serviceCategoryIds[$categoryName] = $category->id;
            }

            foreach ($products as $productData) {
                $product = Product::withoutGlobalScopes()->withTrashed()->firstOrNew([
                    'tenant_id' => $tenant->id,
                    'code' => $productData['code'],
                ]);

                $product->fill([
                    'category_id' => $productCategoryIds[$productData['category']] ?? null,
                    'name' => $productData['name'],
                    'description' => 'Item de reposicao e manutencao para ordens de servico.',
                    'unit' => 'UN',
                    'cost_price' => $productData['cost_price'],
                    'sell_price' => $productData['sell_price'],
                    'stock_qty' => $productData['stock_qty'],
                    'stock_min' => $productData['stock_min'],
                    'track_stock' => true,
                    'is_active' => true,
                ]);
                $product->save();

                if (method_exists($product, 'trashed') && $product->trashed()) {
                    $product->restore();
                }
            }

            foreach ($services as $serviceData) {
                $service = Service::withoutGlobalScopes()->withTrashed()->firstOrNew([
                    'tenant_id' => $tenant->id,
                    'code' => $serviceData['code'],
                ]);

                $service->fill([
                    'category_id' => $serviceCategoryIds[$serviceData['category']] ?? null,
                    'name' => $serviceData['name'],
                    'description' => 'Servico tecnico padrao para operacao de campo.',
                    'default_price' => $serviceData['default_price'],
                    'estimated_minutes' => $serviceData['estimated_minutes'],
                    'is_active' => true,
                ]);
                $service->save();

                if (method_exists($service, 'trashed') && $service->trashed()) {
                    $service->restore();
                }
            }
        }

        $this->command->info(count($products).' produtos e '.count($services).' servicos criados/verificados para '.$tenants->count().' tenant(s)');
    }
}
