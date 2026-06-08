<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega los constraints de foreign keys pendientes en tablas existentes:
     * - work_orders.maintenance_plan_id
     * - asset_meter_readings.maintenance_plan_id
     */
    public function up(): void
    {
        // Agregar FK en work_orders (ya existe la columna, falta el constraint)
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreign('maintenance_plan_id', 'fk_work_orders_maintenance_plan')
                ->references('id')
                ->on('maintenance_plans')
                ->onDelete('set null');
        });

        // Agregar FK en asset_meter_readings
        Schema::table('asset_meter_readings', function (Blueprint $table) {
            $table->foreign('maintenance_plan_id', 'fk_asset_meter_readings_maintenance_plan')
                ->references('id')
                ->on('maintenance_plans')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign('fk_work_orders_maintenance_plan');
        });

        Schema::table('asset_meter_readings', function (Blueprint $table) {
            $table->dropForeign('fk_asset_meter_readings_maintenance_plan');
        });
    }
};
