<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provedor Fiscal Ativo
    |--------------------------------------------------------------------------
    |
    | Qual adapter de emissão fiscal será utilizado.
    | Opções: "focusnfe" | "nuvemfiscal"
    |
    */

    'provider' => env('FISCAL_PROVIDER', 'focusnfe'),

    /*
    |--------------------------------------------------------------------------
    | Focus NF-e
    |--------------------------------------------------------------------------
    |
    | Credenciais e configuração do Focus NF-e (https://focusnfe.com.br).
    | O token é gerado no painel administrativo do Focus NF-e.
    | Ambiente "homologation" para testes, "production" para notas reais.
    |
    */

    'focusnfe' => [
        'token' => env('FOCUSNFE_TOKEN'),
        'environment' => env('FOCUSNFE_ENV', 'homologation'),
        'url_production' => 'https://api.focusnfe.com.br',
        'url_homologation' => 'https://homologacao.focusnfe.com.br',
    ],

    /*
    |--------------------------------------------------------------------------
    | Nuvem Fiscal (alternativa)
    |--------------------------------------------------------------------------
    */

    'nuvemfiscal' => [
        'url' => env('NUVEMFISCAL_URL', 'https://api.nuvemfiscal.com.br'),
        'client_id' => env('NUVEMFISCAL_CLIENT_ID'),
        'client_secret' => env('NUVEMFISCAL_CLIENT_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    |
    | Secret usado para validar callbacks recebidos do provedor fiscal.
    |
    */

    'webhook_secret' => env('FISCAL_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Padrões Fiscais
    |--------------------------------------------------------------------------
    |
    | Valores padrão usados na emissão de documentos fiscais quando não
    | especificados pela configuração do tenant.
    |
    */

    'defaults' => [
        'natureza_operacao' => env('FISCAL_NATUREZA_OPERACAO', 'Prestacao de Servicos'),
        'regime_tributario' => env('FISCAL_REGIME_TRIBUTARIO', '1'), // 1=Simples Nacional, 2=SN Excesso, 3=Regime Normal
    ],

];
