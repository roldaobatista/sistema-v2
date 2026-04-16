<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('central_templates')) {
            Schema::create('central_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('nome', 150);
                $table->text('descricao')->nullable();
                $table->string('tipo', 20)->default('TAREFA');
                $table->string('prioridade', 20)->default('MEDIA');
                $table->string('visibilidade', 20)->default('EQUIPE');
                $table->string('categoria', 60)->nullable();
                $table->integer('due_days')->nullable();
                $table->json('subtasks')->nullable();
                $table->json('default_watchers')->nullable();
                $table->json('tags')->nullable();
                $table->boolean('ativo')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                $table->index(['tenant_id', 'ativo'], 'ct_tenant_active');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('central_templates');
    }
};
