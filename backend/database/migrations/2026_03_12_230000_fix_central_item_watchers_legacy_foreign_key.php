<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('central_item_watchers')) {
            return;
        }

        $hasAgendaItemId = Schema::hasColumn('central_item_watchers', 'agenda_item_id');
        $hasCentralItemId = Schema::hasColumn('central_item_watchers', 'central_item_id');

        if ($hasAgendaItemId || ! $hasCentralItemId) {
            return;
        }

        Schema::table('central_item_watchers', function (Blueprint $table) {
            $table->unsignedBigInteger('agenda_item_id')->nullable();
        });

        DB::table('central_item_watchers')->update([
            'agenda_item_id' => DB::raw('central_item_id'),
        ]);

        $indexes = collect(DB::select('SHOW INDEX FROM central_item_watchers'))
            ->pluck('Key_name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        Schema::table('central_item_watchers', function (Blueprint $table) use ($indexes) {
            if (! in_array('ciw_item_user_unique', $indexes, true)) {
                $table->unique(['agenda_item_id', 'user_id'], 'ciw_item_user_unique');
            }

            if (! in_array('ciw_agenda_item_idx', $indexes, true)) {
                $table->index('agenda_item_id', 'ciw_agenda_item_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('central_item_watchers') || ! Schema::hasColumn('central_item_watchers', 'agenda_item_id')) {
            return;
        }

        $hasCentralItemId = Schema::hasColumn('central_item_watchers', 'central_item_id');

        if (! $hasCentralItemId) {
            return;
        }

        $indexes = collect(DB::select('SHOW INDEX FROM central_item_watchers'))
            ->pluck('Key_name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        Schema::table('central_item_watchers', function (Blueprint $table) use ($indexes) {
            if (in_array('ciw_item_user_unique', $indexes, true)) {
                $table->dropUnique('ciw_item_user_unique');
            }

            if (in_array('ciw_agenda_item_idx', $indexes, true)) {
                $table->dropIndex('ciw_agenda_item_idx');
            }

            $table->dropColumn('agenda_item_id');
        });
    }
};
