<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'pis_number')) {
                $table->string('pis_number', 11)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('users', 'cpf')) {
                $table->string('cpf', 11)->nullable()->after('pis_number');
            }
            if (! Schema::hasColumn('users', 'ctps_number')) {
                $table->string('ctps_number', 20)->nullable()->after('cpf');
            }
            if (! Schema::hasColumn('users', 'ctps_series')) {
                $table->string('ctps_series', 10)->nullable()->after('ctps_number');
            }
            if (! Schema::hasColumn('users', 'admission_date')) {
                $table->date('admission_date')->nullable()->after('ctps_series');
            }
            if (! Schema::hasColumn('users', 'termination_date')) {
                $table->date('termination_date')->nullable()->after('admission_date');
            }
            if (! Schema::hasColumn('users', 'salary')) {
                $table->decimal('salary', 12, 2)->nullable()->after('termination_date');
            }
            if (! Schema::hasColumn('users', 'salary_type')) {
                $table->string('salary_type', 20)->nullable()->default('monthly')->after('salary');
            }
            if (! Schema::hasColumn('users', 'work_shift')) {
                $table->string('work_shift', 50)->nullable()->after('salary_type');
            }
            if (! Schema::hasColumn('users', 'journey_rule_id')) {
                $table->unsignedBigInteger('journey_rule_id')->nullable()->after('work_shift');
                $table->foreign('journey_rule_id')->references('id')->on('journey_rules')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'cbo_code')) {
                $table->string('cbo_code', 10)->nullable()->after('journey_rule_id');
            }
            if (! Schema::hasColumn('users', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('cbo_code');
            }
            if (! Schema::hasColumn('users', 'gender')) {
                $table->string('gender', 10)->nullable()->after('birth_date');
            }
            if (! Schema::hasColumn('users', 'marital_status')) {
                $table->string('marital_status', 20)->nullable()->after('gender');
            }
            if (! Schema::hasColumn('users', 'education_level')) {
                $table->string('education_level', 30)->nullable()->after('marital_status');
            }
            if (! Schema::hasColumn('users', 'nationality')) {
                $table->string('nationality', 50)->nullable()->default('brasileira')->after('education_level');
            }
            if (! Schema::hasColumn('users', 'rg_number')) {
                $table->string('rg_number', 20)->nullable()->after('nationality');
            }
            if (! Schema::hasColumn('users', 'rg_issuer')) {
                $table->string('rg_issuer', 20)->nullable()->after('rg_number');
            }
            if (! Schema::hasColumn('users', 'voter_title')) {
                $table->string('voter_title', 20)->nullable()->after('rg_issuer');
            }
            if (! Schema::hasColumn('users', 'military_cert')) {
                $table->string('military_cert', 20)->nullable()->after('voter_title');
            }
            if (! Schema::hasColumn('users', 'bank_code')) {
                $table->string('bank_code', 10)->nullable()->after('military_cert');
            }
            if (! Schema::hasColumn('users', 'bank_agency')) {
                $table->string('bank_agency', 10)->nullable()->after('bank_code');
            }
            if (! Schema::hasColumn('users', 'bank_account')) {
                $table->string('bank_account', 20)->nullable()->after('bank_agency');
            }
            if (! Schema::hasColumn('users', 'bank_account_type')) {
                $table->string('bank_account_type', 20)->nullable()->default('checking')->after('bank_account');
            }
            if (! Schema::hasColumn('users', 'dependents_count')) {
                $table->integer('dependents_count')->default(0)->after('bank_account_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'pis_number', 'cpf', 'ctps_number', 'ctps_series',
                'admission_date', 'termination_date', 'salary', 'salary_type',
                'work_shift', 'journey_rule_id', 'cbo_code',
                'birth_date', 'gender', 'marital_status', 'education_level',
                'nationality', 'rg_number', 'rg_issuer', 'voter_title', 'military_cert',
                'bank_code', 'bank_agency', 'bank_account', 'bank_account_type',
                'dependents_count',
            ];

            if (Schema::hasColumn('users', 'journey_rule_id')) {
                $table->dropForeign(['journey_rule_id']);
            }

            $existing = array_filter($columns, fn ($col) => Schema::hasColumn('users', $col));
            if (! empty($existing)) {
                $table->dropColumn($existing);
            }
        });
    }
};
