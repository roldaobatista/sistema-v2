<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('embedded_dashboards')) {
            return;
        }

        Schema::create('embedded_dashboards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('provider', 30);
            $table->text('embed_url');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'display_order'], 'embedded_dashboards_tenant_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('embedded_dashboards');
    }
};
