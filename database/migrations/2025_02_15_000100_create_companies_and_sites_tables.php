<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas de empresas (tenant) y sedes: consolidación completa
     * Incluye companies, site_types, company_sites, company_documents
     * y campos geográficos/corporativos de empresas en una sola migración
     */
    public function up(): void
    {
        // Tabla de tipos de sede
        Schema::create('site_types', function (Blueprint $table) {
            $table->id()->comment('PK. Tipo de sede');
            $table->string('code', 50)->unique()->comment('Código del tipo (HEAD_OFFICE, BRANCH, PLANT, WAREHOUSE, etc.)');
            $table->string('name', 120)->comment('Nombre del tipo de sede');
            $table->string('description', 255)->nullable()->comment('Descripción del tipo');
            $table->boolean('is_active')->default(true)->comment('Estado activo del tipo');
            $table->timestamps();
        });

        // Tabla de tamaños de empresa
        Schema::create('company_sizes', function (Blueprint $table) {
            $table->id()->comment('PK. Tamaño de empresa');
            $table->string('code', 50)->unique()->comment('Código (MICRO, SMALL, MEDIUM, LARGE)');
            $table->string('name', 120)->comment('Nombre del tamaño');
            $table->integer('min_employees')->nullable()->comment('Número mínimo de empleados');
            $table->integer('max_employees')->nullable()->comment('Número máximo de empleados');
            $table->boolean('is_active')->default(true)->comment('Estado activo');
            $table->timestamps();
        });

        // Tabla de empresas (tenants)
        Schema::create('companies', function (Blueprint $table) {
            $table->id()->comment('PK. Identificador interno de la empresa (tenant)');
            $table->string('legal_name', 190)->comment('Razón social registrada ante entes estatales');
            $table->string('trade_name', 190)->nullable()->comment('Nombre comercial con el que opera');
            $table->string('tax_id', 50)->nullable()->unique()->comment('Identificador tributario (NIT u otro)');
            
            // Ubicación geográfica
            $table->foreignId('country_id')->nullable()->constrained('countries')->comment('FK a countries.id - País de constitución');
            $table->foreignId('department_geo_id')->nullable()->constrained('departments_geo')->comment('FK a departments_geo.id - Departamento/Estado');
            $table->foreignId('municipality_id')->nullable()->constrained('municipalities')->comment('FK a municipalities.id - Municipio/Ciudad');
            
            // Información empresarial
            $table->foreignId('company_size_id')->nullable()->constrained('company_sizes')->comment('FK a company_sizes.id - Tamaño de empresa');
            $table->string('economic_activity', 255)->nullable()->comment('Actividad económica principal (CIIU u otro)');
            
            // Dirección principal
            $table->string('address_line_1', 190)->nullable()->comment('Dirección principal línea 1');
            $table->string('address_line_2', 190)->nullable()->comment('Dirección principal línea 2');
            $table->string('postal_code', 20)->nullable()->comment('Código postal');
            
            // Datos corporativos
            $table->date('founded_at')->nullable()->comment('Fecha de fundación');
            $table->unsignedInteger('employee_count')->nullable()->comment('Número de empleados');
            
            // Estado y auditoría
            $table->enum('status', ['active', 'inactive'])->default('active')->comment('Estado operativo del tenant');
            $table->timestamps();
            $table->softDeletes()->comment('Borrado lógico (soft delete)');
            
            $table->index('country_id', 'idx_companies_country');
            $table->index(['status', 'tax_id'], 'idx_companies_status_tax');
        });

        // Tabla de sedes de empresa
        Schema::create('company_sites', function (Blueprint $table) {
            $table->id()->comment('PK. Identificador de la sede');
            $table->foreignId('company_id')->constrained('companies')->comment('FK a companies.id');
            $table->foreignId('site_type_id')->nullable()->constrained('site_types')->comment('FK a site_types.id');
            $table->string('name', 190)->comment('Nombre de la sede (Planta, Oficina, etc.)');
            $table->foreignId('municipality_id')->nullable()->constrained('municipalities')->comment('FK a municipalities.id - Municipio normalizado');
            $table->string('address_line_1', 190)->nullable()->comment('Dirección principal');
            $table->string('address_line_2', 190)->nullable()->comment('Dirección complementaria (opcional)');
            $table->string('postal_code', 20)->nullable()->comment('Código postal');
            $table->decimal('latitude', 10, 8)->nullable()->comment('Coordenada de latitud');
            $table->decimal('longitude', 11, 8)->nullable()->comment('Coordenada de longitud');
            $table->boolean('is_headquarters')->default(false)->comment('Indica si es la sede principal');
            $table->boolean('is_active')->default(true)->comment('Estado activo de la sede');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('company_id', 'idx_sites_company');
            $table->index('municipality_id', 'idx_sites_municipality');
            $table->index('site_type_id', 'idx_sites_type');
            $table->index(['company_id', 'is_active'], 'idx_sites_company_active');
        });

        // Tabla de documentos de empresa
        Schema::create('company_documents', function (Blueprint $table) {
            $table->id()->comment('PK. Documento de empresa');
            $table->foreignId('company_id')->constrained('companies')->comment('FK a companies.id');
            $table->foreignId('document_type_id')->constrained('document_types')->comment('FK a document_types.id');
            $table->string('document_number', 50)->comment('Número o código del documento');
            $table->string('file_path', 255)->nullable()->comment('Ruta del archivo almacenado');
            $table->date('issued_at')->nullable()->comment('Fecha de emisión del documento');
            $table->date('expires_at')->nullable()->comment('Fecha de vencimiento del documento');
            $table->text('notes')->nullable()->comment('Notas adicionales');
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['company_id', 'document_type_id', 'document_number'], 'uq_company_doc');
            $table->index('company_id', 'idx_company_docs_company');
            $table->index('document_type_id', 'idx_company_docs_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('company_documents');
        Schema::dropIfExists('company_sites');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('company_sizes');
        Schema::dropIfExists('site_types');
        Schema::enableForeignKeyConstraints();
    }
};
