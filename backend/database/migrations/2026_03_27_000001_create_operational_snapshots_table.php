<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operational_snapshots')) {
            return;
        }

        Schema::create('operational_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('status', 32)->index();
            $table->unsignedInteger('alerts_count')->default(0);
            $table->json('health_payload');
            $table->json('metrics_payload')->nullable();
            $table->json('alerts_payload')->nullable();
            $table->timestamp('captured_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_snapshots');
    }
};
