<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inmetro_history', function (Blueprint $table) {
            if (! Schema::hasColumn('inmetro_history', 'executor_document')) {
                $table->string('executor_document', 20)->nullable()->after('executor')->comment('CNPJ/CPF of the technical assistance that verified or repaired the instrument');
            }
            if (! Schema::hasColumn('inmetro_history', 'osint_threat_level')) {
                $table->string('osint_threat_level', 50)->nullable()->after('notes')->comment('Threat level from OSINT/DarkWeb sources (e.g., safe, suspect, convicted_fraud)');
            }
        });

        Schema::table('inmetro_instruments', function (Blueprint $table) {
            if (! Schema::hasColumn('inmetro_instruments', 'last_scrape_status')) {
                $table->string('last_scrape_status', 50)->nullable()->after('source')->comment('Status of the last deep scrape (success, captcha, timeout)');
            }
            if (! Schema::hasColumn('inmetro_instruments', 'next_deep_scrape_at')) {
                $table->timestamp('next_deep_scrape_at')->nullable()->after('last_scrape_status')->comment('Scheduled date for the next deep scrape based on priority');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inmetro_history', function (Blueprint $table) {
            if (Schema::hasColumn('inmetro_history', 'executor_document')) {
                $table->dropColumn('executor_document');
            }
            if (Schema::hasColumn('inmetro_history', 'osint_threat_level')) {
                $table->dropColumn('osint_threat_level');
            }
        });

        Schema::table('inmetro_instruments', function (Blueprint $table) {
            if (Schema::hasColumn('inmetro_instruments', 'last_scrape_status')) {
                $table->dropColumn('last_scrape_status');
            }
            if (Schema::hasColumn('inmetro_instruments', 'next_deep_scrape_at')) {
                $table->dropColumn('next_deep_scrape_at');
            }
        });
    }
};
