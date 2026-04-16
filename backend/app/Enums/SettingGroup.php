<?php

namespace App\Enums;

enum SettingGroup: string
{
    case GENERAL = 'general';
    case OS = 'os';
    case QUOTES = 'quotes';
    case FINANCIAL = 'financial';
    case NOTIFICATION = 'notification';
    case WHATSAPP = 'whatsapp';
    case SMTP = 'smtp';
    case CRM = 'crm';

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'Geral',
            self::OS => 'Ordens de Serviço',
            self::QUOTES => 'Orçamentos',
            self::FINANCIAL => 'Financeiro',
            self::NOTIFICATION => 'Notificações',
            self::WHATSAPP => 'WhatsApp',
            self::SMTP => 'E-mail / SMTP',
            self::CRM => 'CRM',
        };
    }
}
