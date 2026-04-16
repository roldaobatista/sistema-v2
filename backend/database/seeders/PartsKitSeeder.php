<?php

namespace Database\Seeders;

use App\Models\PartsKit;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PartsKitSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('parts_kits') || ! Schema::hasTable('parts_kit_items')) {
            $this->command->warn('Tabelas de kits de pecas nao encontradas. Seeder ignorado.');

            return;
        }

        $kits = [
            [
                'name' => 'Kit - Preventiva Basica Comercial',
                'description' => 'Itens padrao para preventiva em balanca comercial.',
                'items' => [
                    ['type' => 'product', 'code' => 'PRD-ALCOOL-ISO', 'description' => 'Alcool Isopropilico 500ml', 'quantity' => 1, 'unit_price' => 32],
                    ['type' => 'product', 'code' => 'PRD-LIMP-CONTATO', 'description' => 'Spray Limpa Contato', 'quantity' => 1, 'unit_price' => 42],
                    ['type' => 'product', 'code' => 'PRD-LACRE-SEG', 'description' => 'Lacre de Seguranca', 'quantity' => 6, 'unit_price' => 6],
                    ['type' => 'service', 'code' => 'SRV-MP-LIMPEZA', 'description' => 'Preventiva com Limpeza Geral', 'quantity' => 1, 'unit_price' => 190],
                ],
            ],
            [
                'name' => 'Kit - Troca de Celula 300kg',
                'description' => 'Kit para substituicao de celula de carga 300kg.',
                'items' => [
                    ['type' => 'product', 'code' => 'PRD-CEL-300', 'description' => 'Celula de Carga 300kg', 'quantity' => 1, 'unit_price' => 320],
                    ['type' => 'product', 'code' => 'PRD-CAB-5M', 'description' => 'Cabo Blindado 5m', 'quantity' => 1, 'unit_price' => 55],
                    ['type' => 'product', 'code' => 'PRD-CONECTOR-IP67', 'description' => 'Conector IP67', 'quantity' => 2, 'unit_price' => 22],
                    ['type' => 'service', 'code' => 'SRV-MC-TROCA-CEL', 'description' => 'Troca de Celula de Carga', 'quantity' => 1, 'unit_price' => 280],
                ],
            ],
            [
                'name' => 'Kit - Troca de Celula 1000kg',
                'description' => 'Kit para substituicao de celula de carga 1000kg.',
                'items' => [
                    ['type' => 'product', 'code' => 'PRD-CEL-1000', 'description' => 'Celula de Carga 1000kg', 'quantity' => 1, 'unit_price' => 520],
                    ['type' => 'product', 'code' => 'PRD-CAB-10M', 'description' => 'Cabo Blindado 10m', 'quantity' => 1, 'unit_price' => 89],
                    ['type' => 'product', 'code' => 'PRD-CONECTOR-IP67', 'description' => 'Conector IP67', 'quantity' => 4, 'unit_price' => 22],
                    ['type' => 'service', 'code' => 'SRV-MC-TROCA-CEL', 'description' => 'Troca de Celula de Carga', 'quantity' => 1, 'unit_price' => 280],
                ],
            ],
            [
                'name' => 'Kit - Corretiva Eletronica',
                'description' => 'Kit para reparo eletronico em campo.',
                'items' => [
                    ['type' => 'product', 'code' => 'PRD-PLACA-MAE-A1', 'description' => 'Placa Mae Controladora A1', 'quantity' => 1, 'unit_price' => 690],
                    ['type' => 'product', 'code' => 'PRD-FONTE-12V', 'description' => 'Fonte 12V 5A', 'quantity' => 1, 'unit_price' => 95],
                    ['type' => 'product', 'code' => 'PRD-TECLADO-MEM', 'description' => 'Teclado Membrana 30 teclas', 'quantity' => 1, 'unit_price' => 140],
                    ['type' => 'service', 'code' => 'SRV-MC-DIAG', 'description' => 'Diagnostico Eletronico em Campo', 'quantity' => 1, 'unit_price' => 180],
                ],
            ],
            [
                'name' => 'Kit - Instalacao Comercial',
                'description' => 'Kit de instalacao para balanca comercial.',
                'items' => [
                    ['type' => 'product', 'code' => 'PRD-SUPORTE-INOX', 'description' => 'Suporte Inox para Plataforma', 'quantity' => 1, 'unit_price' => 170],
                    ['type' => 'product', 'code' => 'PRD-PARAFUSO-KIT', 'description' => 'Kit Parafusos e Buchas', 'quantity' => 1, 'unit_price' => 29],
                    ['type' => 'product', 'code' => 'PRD-CAB-5M', 'description' => 'Cabo Blindado 5m', 'quantity' => 1, 'unit_price' => 55],
                    ['type' => 'service', 'code' => 'SRV-INST-COMERCIAL', 'description' => 'Instalacao de Balanca Comercial', 'quantity' => 1, 'unit_price' => 260],
                ],
            ],
            [
                'name' => 'Kit - Comissionamento Industrial',
                'description' => 'Kit para instalacao e comissionamento industrial.',
                'items' => [
                    ['type' => 'product', 'code' => 'PRD-CEL-5000', 'description' => 'Celula de Carga 5000kg', 'quantity' => 4, 'unit_price' => 1120],
                    ['type' => 'product', 'code' => 'PRD-IND-UNIV', 'description' => 'Indicador Digital Universal', 'quantity' => 1, 'unit_price' => 1290],
                    ['type' => 'product', 'code' => 'PRD-CAB-10M', 'description' => 'Cabo Blindado 10m', 'quantity' => 2, 'unit_price' => 89],
                    ['type' => 'service', 'code' => 'SRV-INST-INDUSTRIAL', 'description' => 'Instalacao de Balanca Industrial', 'quantity' => 1, 'unit_price' => 690],
                ],
            ],
            [
                'name' => 'Kit - Atendimento Rapido em Campo',
                'description' => 'Kit minimo para chamado emergencial.',
                'items' => [
                    ['type' => 'product', 'code' => 'PRD-BATERIA-7AH', 'description' => 'Bateria Selada 12V 7Ah', 'quantity' => 1, 'unit_price' => 155],
                    ['type' => 'product', 'code' => 'PRD-CONECTOR-IP67', 'description' => 'Conector IP67', 'quantity' => 2, 'unit_price' => 22],
                    ['type' => 'product', 'code' => 'PRD-LIMP-CONTATO', 'description' => 'Spray Limpa Contato', 'quantity' => 1, 'unit_price' => 42],
                    ['type' => 'service', 'code' => 'SRV-VISITA-HORA', 'description' => 'Hora Tecnica em Campo', 'quantity' => 1, 'unit_price' => 185],
                ],
            ],
            [
                'name' => 'Kit - Entrega com Etiquetagem',
                'description' => 'Kit para entrega final com insumos de etiquetagem.',
                'items' => [
                    ['type' => 'product', 'code' => 'PRD-ETIQ-TER-40', 'description' => 'Etiqueta Termica 40x40', 'quantity' => 2, 'unit_price' => 25],
                    ['type' => 'product', 'code' => 'PRD-BOBINA-80', 'description' => 'Bobina Termica 80mm', 'quantity' => 2, 'unit_price' => 17],
                    ['type' => 'service', 'code' => 'SRV-CONS-TREINAMENTO', 'description' => 'Treinamento de Operadores (4h)', 'quantity' => 1, 'unit_price' => 750],
                ],
            ],
        ];

        $tenants = Tenant::query()->select('id')->get();

        foreach ($tenants as $tenant) {
            $products = Product::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->pluck('id', 'code');

            $services = Service::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->pluck('id', 'code');

            foreach ($kits as $kitData) {
                $kit = PartsKit::withoutGlobalScopes()->withTrashed()->firstOrNew([
                    'tenant_id' => $tenant->id,
                    'name' => $kitData['name'],
                ]);

                $wasNew = ! $kit->exists;

                $kit->fill([
                    'description' => $kitData['description'],
                    'is_active' => true,
                ]);
                $kit->save();

                if (method_exists($kit, 'trashed') && $kit->trashed()) {
                    $kit->restore();
                }

                if (! $wasNew && $kit->items()->exists()) {
                    continue;
                }

                $kit->items()->delete();

                foreach ($kitData['items'] as $itemData) {
                    $referenceId = null;

                    if ($itemData['type'] === 'product') {
                        $referenceId = $products->get($itemData['code']);
                    } else {
                        $referenceId = $services->get($itemData['code']);
                    }

                    $kit->items()->create([
                        'type' => $itemData['type'],
                        'reference_id' => $referenceId,
                        'description' => $itemData['description'],
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                    ]);
                }
            }
        }

        $this->command->info(count($kits).' kits de pecas criados/verificados para '.$tenants->count().' tenant(s)');
    }
}
