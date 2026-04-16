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
        Schema::table('time_clock_entries', function (Blueprint $table) {
            $table->decimal('accuracy_in', 5, 2)->nullable()->comment('Precisão GPS entrada em metros');
            $table->decimal('accuracy_out', 5, 2)->nullable()->comment('Precisão GPS saída em metros');
            $table->decimal('accuracy_break', 5, 2)->nullable()->comment('Precisão GPS intervalo');
            $table->string('address_in', 500)->nullable()->comment('Endereço reverso da entrada');
            $table->string('address_out', 500)->nullable()->comment('Endereço reverso da saída');
            $table->string('address_break', 500)->nullable()->comment('Endereço reverso do intervalo');
            $table->decimal('altitude_in', 10, 2)->nullable()->comment('Altitude entrada');
            $table->decimal('altitude_out', 10, 2)->nullable()->comment('Altitude saída');
            $table->decimal('speed_in', 6, 2)->nullable()->comment('Velocidade no momento (detecta fraude)');
            $table->boolean('location_spoofing_detected')->default(false)->comment('Flag anti-fraude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('time_clock_entries', function (Blueprint $table) {
            $table->dropColumn([
                'accuracy_in',
                'accuracy_out',
                'accuracy_break',
                'address_in',
                'address_out',
                'address_break',
                'altitude_in',
                'altitude_out',
                'speed_in',
                'location_spoofing_detected',
            ]);
        });
    }
};
