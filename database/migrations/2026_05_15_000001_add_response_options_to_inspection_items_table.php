<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inspection_items', function (Blueprint $table) {
            // null = hereda opciones de la sección; array = override por ítem
            $table->json('response_options')->nullable()->after('item_type');
        });
    }

    public function down(): void
    {
        Schema::table('inspection_items', function (Blueprint $table) {
            $table->dropColumn('response_options');
        });
    }
};
