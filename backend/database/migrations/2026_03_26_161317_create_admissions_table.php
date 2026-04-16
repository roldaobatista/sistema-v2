<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('candidate_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('status')->default('candidate_approved');
            $table->date('start_date')->nullable();
            $table->decimal('salary', 10, 2)->nullable();
            $table->boolean('salary_confirmed')->default(false);
            $table->boolean('documents_completed')->default(false);
            $table->string('aso_result')->nullable();
            $table->date('aso_date')->nullable();
            $table->string('esocial_receipt')->nullable();
            $table->boolean('email_provisioned')->default(false);
            $table->boolean('role_assigned')->default(false);
            $table->boolean('mandatory_trainings_completed')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('candidate_id')->references('id')->on('candidates')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};
