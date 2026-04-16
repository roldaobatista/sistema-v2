<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('portal_ticket_messages')) {
            Schema::create('portal_ticket_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('portal_ticket_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->text('message');
                $table->boolean('is_internal')->default(false);
                $table->timestamps();

                $table->foreign('portal_ticket_id')
                    ->references('id')
                    ->on('portal_tickets')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_ticket_messages');
    }
};
