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
        // 1. Warehouses (Almacenes)
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('code', 50); // WH-001
            
            // Identificación
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('warehouse_type', ['main', 'secondary', 'mobile', 'external'])
                ->default('main')
                ->comment('Tipo de almacén');
            
            // Ubicación
            $table->unsignedBigInteger('company_site_id')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            
            // Responsable
            $table->unsignedBigInteger('manager_id')->nullable()->comment('Encargado del almacén');
            
            // Control
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            
            // Auditoría
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('company_id');
            $table->index('code');
            $table->index('company_site_id');
            $table->index('is_active');
            $table->unique(['company_id', 'code']);
            
            // Foreign Keys
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('company_site_id')->references('id')->on('company_sites')->onDelete('set null');
            $table->foreign('manager_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
        });

        // 2. Material Categories (Categorías de Materiales)
        Schema::create('material_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('code', 50); // CAT-001
            $table->string('name', 255);
            $table->text('description')->nullable();
            
            // Jerarquía
            $table->unsignedBigInteger('parent_category_id')->nullable()
                ->comment('Categoría padre para estructura jerárquica');
            
            // Estado
            $table->boolean('is_active')->default(true);
            
            // Auditoría
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('company_id');
            $table->index('code');
            $table->index('parent_category_id');
            $table->index('is_active');
            $table->unique(['company_id', 'code']);
            
            // Foreign Keys
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('parent_category_id')->references('id')->on('material_categories')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
        });

        // 3. Materials (Materiales/Repuestos)
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('material_category_id')->nullable();
            
            // Códigos de identificación
            $table->string('code', 50); // MAT-00001
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('barcode', 100)->nullable();
            $table->string('sku', 100)->nullable();
            $table->string('manufacturer_part_number', 100)->nullable();
            
            // Unidades y costos
            $table->string('unit_of_measure', 50); // unidad, litro, kg, metro, etc.
            $table->decimal('unit_cost', 12, 2)->default(0)->comment('Costo unitario actual');
            
            // Control de stock
            $table->decimal('minimum_stock', 10, 3)->default(0)->comment('Stock mínimo (alerta)');
            $table->decimal('maximum_stock', 10, 3)->nullable()->comment('Stock máximo recomendado');
            $table->decimal('reorder_point', 10, 3)->nullable()->comment('Punto de reorden');
            $table->decimal('reorder_quantity', 10, 3)->nullable()->comment('Cantidad a pedir');
            
            // Proveedor
            $table->string('default_supplier')->nullable();
            
            // Estado
            $table->boolean('is_active')->default(true);
            $table->boolean('is_critical')->default(false)->comment('Material crítico (alerta prioritaria)');
            
            // Tipo de material
            $table->boolean('is_tool')->default(false)->comment('TRUE = Herramienta reusable, FALSE = Material consumible');
            
            // Campos específicos para HERRAMIENTAS
            $table->string('brand', 100)->nullable()->comment('Marca (para herramientas)');
            $table->string('model', 100)->nullable()->comment('Modelo (para herramientas)');
            $table->string('serial_number', 100)->nullable()->comment('Número de serie (para herramientas)');
            $table->enum('tool_status', ['available', 'in_use', 'maintenance', 'damaged', 'retired'])
                ->nullable()
                ->comment('Estado de la herramienta (solo si is_tool=true)');
            
            // Calibración (para herramientas de medición)
            $table->boolean('requires_calibration')->default(false)->comment('Requiere calibración periódica');
            $table->date('last_calibration_date')->nullable();
            $table->date('next_calibration_date')->nullable();
            $table->integer('calibration_frequency_days')->nullable()->comment('Días entre calibraciones');
            
            // Imágenes y notas
            $table->string('image_path', 500)->nullable();
            $table->text('notes')->nullable();
            
            // Auditoría
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('company_id');
            $table->index('code');
            $table->index('barcode');
            $table->index('sku');
            $table->index('material_category_id');
            $table->index('is_active');
            $table->index('is_critical');
            $table->index('is_tool');
            $table->index('serial_number');
            $table->index('tool_status');
            $table->index(['requires_calibration', 'next_calibration_date']);
            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'barcode']);
            $table->unique(['company_id', 'serial_number']);
            
            // Foreign Keys
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('material_category_id')->references('id')->on('material_categories')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
        });

        // 4. Warehouse Stock (Stock por Almacén)
        Schema::create('warehouse_stock', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('material_id');
            
            // Stock actual
            $table->decimal('quantity', 10, 3)->default(0);
            
            // Costo promedio ponderado
            $table->decimal('average_unit_cost', 12, 2)->nullable()
                ->comment('Costo promedio ponderado');
            
            // Ubicación dentro del almacén
            $table->string('location', 100)->nullable()->comment('Ej: Pasillo 3, Estante B, Nivel 2');
            
            // Auditoría
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            
            // Índices
            $table->index('warehouse_id');
            $table->index('material_id');
            $table->index('quantity');
            $table->unique(['warehouse_id', 'material_id']);
            
            // Foreign Keys
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('material_id')->references('id')->on('materials')->onDelete('cascade');
        });

        // 5. Inventory Transactions (Movimientos de Inventario)
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('transaction_code', 50); // INV-YYYYMM-NNNNN
            
            // Tipo de movimiento
            $table->enum('transaction_type', [
                'purchase',        // Compra/Entrada
                'adjustment',      // Ajuste de inventario
                'work_order_out',  // Salida por Work Order (materiales consumibles)
                'work_order_return', // Devolución de sobrantes de Work Order
                'tool_assignment', // Asignación de herramienta (NO descuenta stock)
                'tool_return',     // Devolución de herramienta (NO suma stock)
                'return',          // Devolución genérica
                'transfer',        // Transferencia entre almacenes
                'damage',          // Daño/Pérdida
                'initial'          // Inventario inicial
            ]);
            
            // Relaciones
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('material_id');
            
            // Cantidades y costos
            $table->decimal('quantity', 10, 3)->comment('Positivo = entrada, Negativo = salida');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->decimal('total_cost', 12, 2)->nullable();
            
            // Balance después de la transacción
            $table->decimal('balance_after', 10, 3)->comment('Stock resultante después de la transacción');
            
            // Información adicional
            $table->text('reason')->nullable()->comment('Motivo del movimiento');
            $table->string('reference_document', 100)->nullable()->comment('Factura, Acta, etc.');
            $table->string('purchase_order_number', 50)->nullable();
            
            // Transferencias entre almacenes
            $table->unsignedBigInteger('from_warehouse_id')->nullable();
            $table->unsignedBigInteger('to_warehouse_id')->nullable();
            
            // Relación con Work Order
            $table->unsignedBigInteger('work_order_id')->nullable();
            
            // Auditoría
            $table->dateTime('transaction_date');
            $table->unsignedBigInteger('performed_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
            
            // Índices
            $table->index('company_id');
            $table->index('transaction_code');
            $table->index('transaction_type');
            $table->index('warehouse_id');
            $table->index('material_id');
            $table->index('transaction_date');
            $table->index('from_warehouse_id');
            $table->index('to_warehouse_id');
            $table->index('work_order_id');
            $table->unique(['company_id', 'transaction_code']);
            
            // Foreign Keys
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');
            $table->foreign('material_id')->references('id')->on('materials')->onDelete('restrict');
            $table->foreign('from_warehouse_id')->references('id')->on('warehouses')->onDelete('set null');
            $table->foreign('to_warehouse_id')->references('id')->on('warehouses')->onDelete('set null');
            $table->foreign('performed_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            // NOTA: work_order_id FK se agregará después en una migración separada
            // porque work_orders se crea después de inventory_transactions
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
        Schema::dropIfExists('warehouse_stock');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('material_categories');
        Schema::dropIfExists('warehouses');
    }
};
