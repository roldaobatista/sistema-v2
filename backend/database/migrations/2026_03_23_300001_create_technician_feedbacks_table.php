<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('work_order_id')->nullable()->index();
            $table->date('date');
            $table->string('type', 30)->default('general');
            $table->text('message');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_feedbacks');
    }
};
