<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // CrmActivity channels: Portuguese → English lowercase
        DB::table('crm_activities')->where('channel', 'telefone')->update(['channel' => 'phone']);
        DB::table('crm_activities')->where('channel', 'presencial')->update(['channel' => 'in_person']);
    }

    public function down(): void
    {
        DB::table('crm_activities')->where('channel', 'phone')->update(['channel' => 'telefone']);
        DB::table('crm_activities')->where('channel', 'in_person')->update(['channel' => 'presencial']);
    }
};
