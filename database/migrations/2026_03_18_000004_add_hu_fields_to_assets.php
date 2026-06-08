<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega campos a la tabla assets requeridos por HU-A1, HU-A3 y HU-A5:
     *
     * - production_line_id  (HU-A1)  — línea de producción/área del proceso
     * - manufacturer_id     (HU-A5)  — fabricante del equipo (FK a asset_vendors)
     * - supplier_id         (HU-A5)  — proveedor del equipo (FK a asset_vendors)
     * - installation_date   (HU-A5)  — fecha de instalación del equipo
     * - end_of_life_date    (HU-A5)  — fecha estimada de fin de vida útil
     *
     * Nota HU-A3: los hijos heredan production_line_id, company_site_id y category_id
     * del activo padre automáticamente (lógica de negocio en el controlador).
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // HU-A1: línea de producción (después de company_site_id)
            $table->foreignId('production_line_id')
                ->nullable()
                ->after('company_site_id')
                ->constrained('production_lines')
                ->onDelete('set null')
                ->comment('FK a production_lines.id — línea de producción o área del proceso (HU-A1)');

            // HU-A5: fabricante (después de serial_number)
            $table->foreignId('manufacturer_id')
                ->nullable()
                ->after('serial_number')
                ->constrained('asset_vendors')
                ->onDelete('set null')
                ->comment('FK a asset_vendors.id — fabricante del equipo (HU-A5)');

            // HU-A5: proveedor (después de manufacturer_id)
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('manufacturer_id')
                ->constrained('asset_vendors')
                ->onDelete('set null')
                ->comment('FK a asset_vendors.id — proveedor del equipo (HU-A5)');

            // HU-A5: fecha de instalación (después de purchase_date)
            $table->date('installation_date')
                ->nullable()
                ->after('purchase_date')
                ->comment('Fecha de instalación del equipo en planta (HU-A5)');

            // HU-A5: fin de vida útil estimada (después de installation_date)
            $table->date('end_of_life_date')
                ->nullable()
                ->after('installation_date')
                ->comment('Fecha estimada de fin de vida útil — permite calcular tiempo transcurrido (HU-A5)');

            // Índices
            $table->index('production_line_id', 'idx_assets_production_line');
            $table->index('manufacturer_id', 'idx_assets_manufacturer');
            $table->index('supplier_id', 'idx_assets_supplier');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['production_line_id']);
            $table->dropForeign(['manufacturer_id']);
            $table->dropForeign(['supplier_id']);
            $table->dropIndex('idx_assets_production_line');
            $table->dropIndex('idx_assets_manufacturer');
            $table->dropIndex('idx_assets_supplier');
            $table->dropColumn([
                'production_line_id',
                'manufacturer_id',
                'supplier_id',
                'installation_date',
                'end_of_life_date',
            ]);
        });
    }
};
