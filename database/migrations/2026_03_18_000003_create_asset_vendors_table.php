<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla: asset_vendors
     *
     * Catálogo de fabricantes y proveedores de equipos, parametrizable por empresa.
     * Un mismo registro puede actuar como fabricante, proveedor o ambos.
     *
     * Requerido por HU-A5 — campos "Fabricante" y "Proveedor" del activo.
     */
    public function up(): void
    {
        Schema::create('asset_vendors', function (Blueprint $table) {
            $table->id()->comment('PK. Fabricante o proveedor');
            $table->foreignId('company_id')
                ->constrained('companies')
                ->onDelete('cascade')
                ->comment('FK a companies.id — empresa propietaria');
            $table->string('code', 50)->comment('Código único por empresa');
            $table->string('name', 150)->comment('Nombre del fabricante o proveedor');
            $table->enum('type', ['manufacturer', 'supplier', 'both'])
                ->default('both')
                ->comment('Rol del vendor: fabricante, proveedor o ambos');
            $table->string('contact_name', 100)->nullable()->comment('Nombre de la persona de contacto');
            $table->string('contact_email', 150)->nullable()->comment('Correo de contacto');
            $table->string('contact_phone', 50)->nullable()->comment('Teléfono de contacto');
            $table->text('notes')->nullable()->comment('Notas adicionales');
            $table->boolean('is_active')->default(true)->comment('Estado activo');
            $table->timestamps();
            $table->softDeletes()->comment('Borrado lógico');

            $table->unique(['company_id', 'code'], 'uq_vendor_code_company');
            $table->index('company_id', 'idx_asset_vendors_company');
            $table->index(['company_id', 'type'], 'idx_asset_vendors_company_type');
            $table->index(['company_id', 'is_active'], 'idx_asset_vendors_company_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_vendors');
    }
};
