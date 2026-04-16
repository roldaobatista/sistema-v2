<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FleetOperationalSchemaTest extends TestCase
{
    public function test_fleet_operational_tables_have_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('fuel_logs'));
        $this->assertTrue(Schema::hasTable('vehicle_tires'));
        $this->assertTrue(Schema::hasTable('vehicle_pool_requests'));
        $this->assertTrue(Schema::hasTable('vehicle_accidents'));

        $this->assertTrue(Schema::hasColumn('fuel_logs', 'tenant_id'));
        $this->assertTrue(Schema::hasColumn('fuel_logs', 'fleet_vehicle_id'));
        $this->assertTrue(Schema::hasColumn('fuel_logs', 'date'));
        $this->assertTrue(Schema::hasColumn('fuel_logs', 'total_value'));

        $this->assertTrue(Schema::hasColumn('vehicle_tires', 'tenant_id'));
        $this->assertTrue(Schema::hasColumn('vehicle_tires', 'fleet_vehicle_id'));
        $this->assertTrue(Schema::hasColumn('vehicle_tires', 'position'));
        $this->assertTrue(Schema::hasColumn('vehicle_tires', 'status'));

        $this->assertTrue(Schema::hasColumn('vehicle_pool_requests', 'tenant_id'));
        $this->assertTrue(Schema::hasColumn('vehicle_pool_requests', 'user_id'));
        $this->assertTrue(Schema::hasColumn('vehicle_pool_requests', 'requested_start'));
        $this->assertTrue(Schema::hasColumn('vehicle_pool_requests', 'status'));

        $this->assertTrue(Schema::hasColumn('vehicle_accidents', 'tenant_id'));
        $this->assertTrue(Schema::hasColumn('vehicle_accidents', 'fleet_vehicle_id'));
        $this->assertTrue(Schema::hasColumn('vehicle_accidents', 'occurrence_date'));
        $this->assertTrue(Schema::hasColumn('vehicle_accidents', 'status'));
    }
}
