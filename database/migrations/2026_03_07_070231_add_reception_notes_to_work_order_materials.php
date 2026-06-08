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
        Schema::table('work_order_materials', function (Blueprint $table) {
            $table->text('reception_notes')->nullable()->after('return_notes')->comment('Notas de recepción por almacenista');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_order_materials', function (Blueprint $table) {
            $table->dropColumn('reception_notes');
        });
    }
};
