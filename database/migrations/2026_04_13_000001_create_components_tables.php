<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tipos de componentes (ROD → Rodamiento, MOT → Motor, BAN → Banda)
        Schema::create('component_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('code_prefix', 10)->comment('Prefijo del código: ROD, MOT, BAN, etc.');
            $table->string('name', 100)->comment('Nombre del tipo: Rodamiento, Motor, Banda…');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('is_active');
            $table->unique(['company_id', 'code_prefix']);
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
        });

        // 2. Catálogo de componentes (equivalente a materials para activos)
        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('component_type_id');

            // Identificación
            $table->string('code', 50)->comment('Código autogenerado: ROD-001, MOT-002…');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('reference', 100)->nullable()->comment('Referencia técnica del fabricante');
            $table->string('brand', 100)->nullable();

            // Unidades y costo
            $table->string('unit_of_measure', 50)->comment('Unidad: unidad, par, set…');
            $table->decimal('unit_cost', 12, 2)->default(0);

            // Control de stock
            $table->decimal('minimum_stock', 10, 3)->default(0);
            $table->decimal('maximum_stock', 10, 3)->nullable();
            $table->decimal('reorder_point', 10, 3)->nullable();

            // Estado
            $table->boolean('is_active')->default(true);
            $table->boolean('is_critical')->default(false);

            // Extras
            $table->string('image_path', 500)->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('component_type_id');
            $table->index('code');
            $table->index('is_active');
            $table->index('is_critical');
            $table->unique(['company_id', 'code']);
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('component_type_id')->references('id')->on('component_types')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
        });

        // 3. Stock de componentes por almacén (equivalente a warehouse_stock)
        Schema::create('component_warehouse_stock', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('component_id');
            $table->decimal('quantity', 10, 3)->default(0);
            $table->decimal('average_unit_cost', 12, 2)->nullable();
            $table->string('location', 100)->nullable()->comment('Ej: Estante A, Nivel 2');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('warehouse_id');
            $table->index('component_id');
            $table->index('quantity');
            $table->unique(['warehouse_id', 'component_id']);
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('component_id')->references('id')->on('components')->onDelete('cascade');
        });

        // 4. Componentes asociados a activos
        Schema::create('asset_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('component_id');

            // Cantidades
            $table->decimal('specified_quantity', 10, 3)->default(1)
                ->comment('Cantidad requerida por diseño/especificación técnica');
            $table->decimal('installed_quantity', 10, 3)->default(0)
                ->comment('Cantidad instalada actualmente');

            // Estado computado (se actualiza al modificar installed_quantity)
            $table->enum('status', ['normal', 'low_stock', 'out_of_stock'])
                ->default('normal')
                ->comment('normal: instalado >= especificado; low_stock: 0 < instalado < especificado; out_of_stock: instalado = 0');

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->index('asset_id');
            $table->index('component_id');
            $table->index('status');
            $table->unique(['asset_id', 'component_id']);
            $table->foreign('asset_id')->references('id')->on('assets')->onDelete('cascade');
            $table->foreign('component_id')->references('id')->on('components')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
        });

        // 5. Historial de consumo de componentes por activo
        Schema::create('asset_component_consumptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('component_id');
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->unsignedBigInteger('warehouse_id');

            $table->decimal('quantity_consumed', 10, 3);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->decimal('total_cost', 12, 2)->nullable();

            // Balance stock en el almacén después del movimiento
            $table->decimal('stock_after', 10, 3)->nullable();

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('performed_by');
            $table->dateTime('consumed_at');
            $table->timestamps();

            $table->index('company_id');
            $table->index('asset_id');
            $table->index('component_id');
            $table->index('work_order_id');
            $table->index('warehouse_id');
            $table->index('consumed_at');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('asset_id')->references('id')->on('assets')->onDelete('cascade');
            $table->foreign('component_id')->references('id')->on('components')->onDelete('restrict');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');
            $table->foreign('performed_by')->references('id')->on('users')->onDelete('restrict');
            // work_order_id FK omitida intencionalmente (tabla creada antes que work_orders en algunos entornos)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_component_consumptions');
        Schema::dropIfExists('asset_components');
        Schema::dropIfExists('component_warehouse_stock');
        Schema::dropIfExists('components');
        Schema::dropIfExists('component_types');
    }
};
