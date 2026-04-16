<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('account_receivable_installments', 'psp_external_id')) {
            return;
        }

        Schema::table('account_receivable_installments', function (Blueprint $table) {
            $table->string('psp_external_id')->nullable()->after('status')->index();
            $table->string('psp_status', 30)->nullable()->after('psp_external_id');
            $table->text('psp_boleto_url')->nullable()->after('psp_status');
            $table->string('psp_boleto_barcode')->nullable()->after('psp_boleto_url');
            $table->text('psp_pix_qr_code')->nullable()->after('psp_boleto_barcode');
            $table->text('psp_pix_copy_paste')->nullable()->after('psp_pix_qr_code');
        });
    }

    public function down(): void
    {
        Schema::table('account_receivable_installments', function (Blueprint $table) {
            $table->dropColumn([
                'psp_external_id',
                'psp_status',
                'psp_boleto_url',
                'psp_boleto_barcode',
                'psp_pix_qr_code',
                'psp_pix_copy_paste',
            ]);
        });
    }
};
