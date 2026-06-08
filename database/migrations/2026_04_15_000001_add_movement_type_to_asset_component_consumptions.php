<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_component_consumptions', function (Blueprint $table) {
            // installation: +installed_qty, -stock
            // replacement:  installed_qty sin cambio, -stock
            // removal:      -installed_qty, +stock (devuelve al almacén)
            $table->string('movement_type', 20)->default('replacement')->after('warehouse_id');
            $table->decimal('quantity_delta', 10, 3)->nullable()->after('quantity_consumed'); // cambio en installed_quantity
            $table->boolean('returns_to_stock')->default(false)->after('quantity_delta');

            // warehouse_id puede ser null en removals sin devolución futura
            $table->unsignedBigInteger('warehouse_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('asset_component_consumptions', function (Blueprint $table) {
            $table->dropColumn(['movement_type', 'quantity_delta', 'returns_to_stock']);
            $table->unsignedBigInteger('warehouse_id')->nullable(false)->change();
        });
    }
};
