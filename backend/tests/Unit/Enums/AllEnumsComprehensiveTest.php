<?php

namespace Tests\Unit\Enums;

use App\Enums\AgendaItemOrigin;
use App\Enums\AgendaItemPriority;
use App\Enums\AgendaItemStatus;
use App\Enums\AgendaItemType;
use App\Enums\AgendaItemVisibility;
use App\Enums\CommissionEventStatus;
use App\Enums\CustomerRating;
use App\Enums\DealStatus;
use App\Enums\EquipmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\TenantStatus;
use PHPUnit\Framework\TestCase;

/**
 * Testes profundos de TODOS os enums menores do sistema.
 * Garante que label(), color(), e contagem estão corretos.
 */
class AllEnumsComprehensiveTest extends TestCase
{
    // ═══ TenantStatus ═══

    public function test_tenant_status_active_label(): void
    {
        $this->assertNotEmpty(TenantStatus::ACTIVE->label());
    }

    public function test_tenant_status_active_value(): void
    {
        $this->assertEquals('active', TenantStatus::ACTIVE->value);
    }

    public function test_tenant_status_count(): void
    {
        $this->assertGreaterThanOrEqual(3, count(TenantStatus::cases()));
    }

    public function test_tenant_status_try_from(): void
    {
        $this->assertEquals(TenantStatus::ACTIVE, TenantStatus::tryFrom('active'));
        $this->assertNull(TenantStatus::tryFrom('nonexistent'));
    }

    // ═══ EquipmentStatus ═══

    public function test_equipment_status_has_label(): void
    {
        foreach (EquipmentStatus::cases() as $s) {
            $this->assertNotEmpty($s->label());
        }
    }

    public function test_equipment_status_has_color(): void
    {
        foreach (EquipmentStatus::cases() as $s) {
            $this->assertNotEmpty($s->color());
        }
    }

    // ═══ DealStatus ═══

    public function test_deal_status_labels(): void
    {
        foreach (DealStatus::cases() as $s) {
            $this->assertNotEmpty($s->label());
        }
    }

    public function test_deal_status_colors(): void
    {
        foreach (DealStatus::cases() as $s) {
            $this->assertNotEmpty($s->color());
        }
    }

    // ═══ CommissionEventStatus ═══

    public function test_commission_event_status_labels(): void
    {
        foreach (CommissionEventStatus::cases() as $s) {
            $this->assertNotEmpty($s->label());
        }
    }

    public function test_commission_event_status_has_pending(): void
    {
        $this->assertEquals('pending', CommissionEventStatus::PENDING->value);
    }

    public function test_commission_event_status_has_approved(): void
    {
        $this->assertEquals('approved', CommissionEventStatus::APPROVED->value);
    }

    // ═══ AgendaItemStatus ═══

    public function test_agenda_item_status_labels(): void
    {
        foreach (AgendaItemStatus::cases() as $s) {
            $this->assertNotEmpty($s->label());
        }
    }

    public function test_agenda_item_status_count(): void
    {
        $this->assertGreaterThanOrEqual(4, count(AgendaItemStatus::cases()));
    }

    // ═══ AgendaItemPriority ═══

    public function test_agenda_item_priority_labels(): void
    {
        foreach (AgendaItemPriority::cases() as $p) {
            $this->assertNotEmpty($p->label());
        }
    }

    public function test_agenda_item_priority_has_alta(): void
    {
        $this->assertNotNull(AgendaItemPriority::ALTA);
    }

    public function test_agenda_item_priority_has_media(): void
    {
        $this->assertNotNull(AgendaItemPriority::MEDIA);
    }

    // ═══ AgendaItemType ═══

    public function test_agenda_item_type_labels(): void
    {
        foreach (AgendaItemType::cases() as $t) {
            $this->assertNotEmpty($t->label());
        }
    }

    // ═══ AgendaItemOrigin ═══

    public function test_agenda_item_origin_labels(): void
    {
        foreach (AgendaItemOrigin::cases() as $o) {
            $this->assertNotEmpty($o->label());
        }
    }

    // ═══ AgendaItemVisibility ═══

    public function test_agenda_item_visibility_labels(): void
    {
        foreach (AgendaItemVisibility::cases() as $v) {
            $this->assertNotEmpty($v->label());
        }
    }

    // ═══ CustomerRating ═══

    public function test_customer_rating_labels(): void
    {
        foreach (CustomerRating::cases() as $r) {
            $this->assertNotEmpty($r->label());
        }
    }

    public function test_customer_rating_has_cases(): void
    {
        $this->assertGreaterThanOrEqual(3, count(CustomerRating::cases()));
    }

    // ═══ InvoiceStatus ═══

    public function test_invoice_status_labels(): void
    {
        foreach (InvoiceStatus::cases() as $s) {
            $this->assertNotEmpty($s->label());
        }
    }

    public function test_invoice_status_colors(): void
    {
        foreach (InvoiceStatus::cases() as $s) {
            $this->assertNotEmpty($s->color());
        }
    }
}
