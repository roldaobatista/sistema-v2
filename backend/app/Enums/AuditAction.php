<?php

namespace App\Enums;

enum AuditAction: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';
    case LOGIN = 'login';
    case LOGOUT = 'logout';
    case STATUS_CHANGED = 'status_changed';
    case COMMENTED = 'commented';
    case TENANT_SWITCH = 'tenant_switch';
    case PASSWORD_RESET = 'password_reset';
    case INTERNAL_APPROVED = 'internal_approved';
    case EXPIRATION_ALERT = 'expiration_alert';
    case FOLLOWUP_REMINDER = 'followup_reminder';
    case EMAIL_SENT = 'email_sent';
    case EMAIL_FAILED = 'email_failed';
    case PUBLIC_VIEWED = 'public_viewed';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Criado',
            self::UPDATED => 'Atualizado',
            self::DELETED => 'Excluído',
            self::LOGIN => 'Login',
            self::LOGOUT => 'Logout',
            self::STATUS_CHANGED => 'Status Alterado',
            self::COMMENTED => 'Comentário',
            self::TENANT_SWITCH => 'Troca de Empresa',
            self::PASSWORD_RESET => 'Senha Resetada',
            self::INTERNAL_APPROVED => 'Aprovação Interna',
            self::EXPIRATION_ALERT => 'Alerta de Expiração',
            self::FOLLOWUP_REMINDER => 'Lembrete de Follow-up',
            self::EMAIL_SENT => 'E-mail Enviado',
            self::EMAIL_FAILED => 'Falha no E-mail',
            self::PUBLIC_VIEWED => 'Visualização Pública',
        };
    }
}
