<?php

namespace App\Enums;

enum TimeClassificationType: string
{
    case JORNADA_NORMAL = 'jornada_normal';
    case HORA_EXTRA = 'hora_extra';
    case INTERVALO = 'intervalo';
    case DESLOCAMENTO_CLIENTE = 'deslocamento_cliente';
    case DESLOCAMENTO_ENTRE = 'deslocamento_entre';
    case ESPERA_LOCAL = 'espera_local';
    case EXECUCAO_SERVICO = 'execucao_servico';
    case ALMOCO_VIAGEM = 'almoco_viagem';
    case PERNOITE = 'pernoite';
    case SOBREAVISO = 'sobreaviso';
    case PLANTAO = 'plantao';
    case TEMPO_IMPRODUTIVO = 'tempo_improdutivo';
    case AUSENCIA = 'ausencia';
    case ATESTADO = 'atestado';
    case FOLGA = 'folga';
    case COMPENSACAO = 'compensacao';
    case ADICIONAL_NOTURNO = 'adicional_noturno';
    case DSR = 'dsr';

    public function label(): string
    {
        return match ($this) {
            self::JORNADA_NORMAL => 'Jornada Normal',
            self::HORA_EXTRA => 'Hora Extra',
            self::INTERVALO => 'Intervalo',
            self::DESLOCAMENTO_CLIENTE => 'Deslocamento ao Cliente',
            self::DESLOCAMENTO_ENTRE => 'Deslocamento Entre Clientes',
            self::ESPERA_LOCAL => 'Espera no Local',
            self::EXECUCAO_SERVICO => 'Execução de Serviço',
            self::ALMOCO_VIAGEM => 'Almoço/Refeição em Viagem',
            self::PERNOITE => 'Pernoite',
            self::SOBREAVISO => 'Sobreaviso',
            self::PLANTAO => 'Plantão',
            self::TEMPO_IMPRODUTIVO => 'Tempo Improdutivo',
            self::AUSENCIA => 'Ausência',
            self::ATESTADO => 'Atestado',
            self::FOLGA => 'Folga',
            self::COMPENSACAO => 'Compensação',
            self::ADICIONAL_NOTURNO => 'Adicional Noturno',
            self::DSR => 'DSR',
        };
    }

    public function isWorkTime(): bool
    {
        return in_array($this, [
            self::JORNADA_NORMAL,
            self::HORA_EXTRA,
            self::EXECUCAO_SERVICO,
            self::ADICIONAL_NOTURNO,
        ]);
    }

    public function isPaidTime(): bool
    {
        return in_array($this, [
            self::JORNADA_NORMAL,
            self::HORA_EXTRA,
            self::EXECUCAO_SERVICO,
            self::ADICIONAL_NOTURNO,
            self::SOBREAVISO,
            self::PLANTAO,
            self::DSR,
        ]);
    }

    public function isAbsence(): bool
    {
        return in_array($this, [
            self::AUSENCIA,
            self::ATESTADO,
            self::FOLGA,
            self::COMPENSACAO,
        ]);
    }
}
