<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'image_url')) {
                $table->string('image_url', 500)->nullable();
            }
            if (! Schema::hasColumn('products', 'barcode')) {
                $table->string('barcode', 50)->nullable();
            }
            if (! Schema::hasColumn('products', 'brand')) {
                $table->string('brand', 100)->nullable();
            }
            if (! Schema::hasColumn('products', 'weight')) {
                $table->decimal('weight', 10, 3)->nullable();
            }
            if (! Schema::hasColumn('products', 'width')) {
                $table->decimal('width', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'height')) {
                $table->decimal('height', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('products', 'depth')) {
                $table->decimal('depth', 10, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $columns = ['image_url', 'barcode', 'brand', 'weight', 'width', 'height', 'depth'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
