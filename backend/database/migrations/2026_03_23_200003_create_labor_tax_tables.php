<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inss_brackets', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->decimal('min_salary', 12, 2);
            $table->decimal('max_salary', 12, 2);
            $table->decimal('rate', 5, 2); // percentage
            $table->decimal('deduction', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['year', 'min_salary']);
        });

        Schema::create('irrf_brackets', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->decimal('min_base', 12, 2);
            $table->decimal('max_base', 12, 2)->nullable(); // null = unlimited
            $table->decimal('rate', 5, 2); // percentage
            $table->decimal('deduction', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['year', 'min_base']);
        });

        Schema::create('minimum_wages', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->integer('month');
            $table->decimal('value', 12, 2);
            $table->timestamps();
            $table->unique(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minimum_wages');
        Schema::dropIfExists('irrf_brackets');
        Schema::dropIfExists('inss_brackets');
    }
};
