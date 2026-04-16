<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rr_studies')) {
            Schema::create('rr_studies', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->string('title');
                $table->unsignedBigInteger('instrument_id')->nullable()->index();
                $table->string('parameter')->nullable();
                $table->json('operators')->nullable();
                $table->integer('repetitions')->default(0);
                $table->string('status')->default('draft');
                $table->json('results')->nullable();
                $table->text('conclusion')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rr_studies');
    }
};
