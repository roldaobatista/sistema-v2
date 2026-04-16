<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'equipment_types',
        'equipment_brands',
        'service_types',
        'payment_terms',
        'quote_sources',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                Schema::create($tableName, function (Blueprint $table) use ($tableName) {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable();
                    $table->string('name');
                    $table->string('slug');
                    $table->string('description')->nullable();
                    $table->string('color', 20)->nullable();
                    $table->string('icon', 50)->nullable();
                    $table->boolean('is_active')->default(true);
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->timestamps();
                    $table->softDeletes();

                    $table->index('tenant_id', $tableName.'_tid_idx');
                    $table->unique(['tenant_id', 'slug'], $tableName.'_tid_slug_uq');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            Schema::dropIfExists($table);
        }
    }
};
