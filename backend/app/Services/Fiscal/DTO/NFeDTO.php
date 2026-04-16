<?php

namespace App\Services\Fiscal\DTO;

/**
 * DTO estrito para emissão de NF-e.
 * O Core e o módulo Financeiro dependem apenas deste DTO; o adapter converte para o formato da API externa.
 */
final readonly class NFeDTO
{
    public function __construct(
        public string $reference,
        public string $naturezaOperacao,
        public string $formaPagamento,
        public string $tipoDocumento,
        public string $finalidadeEmissao,
        public string $consumidorFinal,
        public string $presencaComprador,
        public array $emitente,
        public array $destinatario,
        /** @var array<int, array<string, mixed>> */
        public array $items,
        public array $formasPagamento,
        public ?string $informacoesAdicionaisContribuinte = null,
    ) {}

    /**
     * Cria o DTO a partir do payload retornado por NFeDataBuilder::build().
     * Permite que o controller continue usando o builder e apenas troque a assinatura para o gateway.
     */
    public static function fromBuiltPayload(array $payload): self
    {
        return new self(
            reference: $payload['ref'] ?? '',
            naturezaOperacao: $payload['natureza_operacao'] ?? 'Venda de mercadoria',
            formaPagamento: $payload['forma_pagamento'] ?? '0',
            tipoDocumento: $payload['tipo_documento'] ?? '1',
            finalidadeEmissao: $payload['finalidade_emissao'] ?? '1',
            consumidorFinal: $payload['consumidor_final'] ?? '0',
            presencaComprador: $payload['presenca_comprador'] ?? '1',
            emitente: [
                'cnpj_emitente' => $payload['cnpj_emitente'] ?? '',
                'inscricao_estadual' => $payload['inscricao_estadual'] ?? '',
                'regime_tributario' => $payload['regime_tributario'] ?? '1',
            ],
            destinatario: self::extractDestinatario($payload),
            items: $payload['items'] ?? [],
            formasPagamento: $payload['formas_pagamento'] ?? [],
            informacoesAdicionaisContribuinte: $payload['informacoes_adicionais_contribuinte'] ?? null,
        );
    }

    private static function extractDestinatario(array $payload): array
    {
        $keys = [
            'nome_destinatario', 'cnpj_destinatario', 'cpf_destinatario',
            'inscricao_estadual_destinatario', 'indicador_inscricao_estadual_destinatario',
            'email_destinatario', 'telefone_destinatario', 'logradouro_destinatario',
            'numero_destinatario', 'complemento_destinatario', 'bairro_destinatario',
            'municipio_destinatario', 'uf_destinatario', 'cep_destinatario',
            'pais_destinatario', 'codigo_municipio_destinatario',
        ];
        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                $out[$key] = $payload[$key];
            }
        }

        return $out;
    }

    /**
     * Converte o DTO para array no formato esperado por APIs REST (snake_case).
     * O adapter pode usar este método para montar o body da requisição HTTP.
     */
    public function toArray(): array
    {
        $arr = [
            'ref' => $this->reference,
            'natureza_operacao' => $this->naturezaOperacao,
            'forma_pagamento' => $this->formaPagamento,
            'tipo_documento' => $this->tipoDocumento,
            'finalidade_emissao' => $this->finalidadeEmissao,
            'consumidor_final' => $this->consumidorFinal,
            'presenca_comprador' => $this->presencaComprador,
            'cnpj_emitente' => $this->emitente['cnpj_emitente'] ?? '',
            'inscricao_estadual' => $this->emitente['inscricao_estadual'] ?? '',
            'regime_tributario' => $this->emitente['regime_tributario'] ?? '1',
            'items' => $this->items,
            'formas_pagamento' => $this->formasPagamento,
        ];
        foreach ($this->destinatario as $k => $v) {
            $arr[$k] = $v;
        }
        if ($this->informacoesAdicionaisContribuinte !== null) {
            $arr['informacoes_adicionais_contribuinte'] = $this->informacoesAdicionaisContribuinte;
        }

        return $arr;
    }
}
