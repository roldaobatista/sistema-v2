<?php

namespace App\Enums;

enum AgendaItemType: string
{
    case CHAMADO = 'service_call';
    case OS = 'work_order';
    case ORDEM_SERVICO = 'ordem_servico';
    case FINANCEIRO = 'financial';
    case ORCAMENTO = 'quote';
    case TAREFA = 'tarefa';
    case TASK = 'task';
    case LEMBRETE = 'reminder';
    case CALIBRACAO = 'calibration';
    case CONTRATO = 'contract';
    case OUTRO = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CHAMADO => 'Chamado Técnico',
            self::OS => 'Ordem de Serviço',
            self::ORDEM_SERVICO => 'Ordem de Serviço',
            self::FINANCEIRO => 'Financeiro',
            self::ORCAMENTO => 'Orçamento',
            self::TAREFA => 'Tarefa',
            self::TASK => 'Tarefa',
            self::LEMBRETE => 'Lembrete',
            self::CALIBRACAO => 'Calibração',
            self::CONTRATO => 'Contrato',
            self::OUTRO => 'Outro',
        };
    }
}
