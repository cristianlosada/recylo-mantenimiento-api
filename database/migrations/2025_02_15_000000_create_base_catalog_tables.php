<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas base del catálogo: países, departamentos, municipios, tipos de documentos, tipos de contacto
     * Se consolidan aquí para evitar migraciones dispersas de "add/enhance"
     */
    public function up(): void
    {
        // Tabla de países
        Schema::create('countries', function (Blueprint $table) {
            $table->id()->comment('PK. Identificador del país');
            $table->string('code', 3)->unique()->comment('Código ISO 3166-1 del país (COL, USA, etc.)');
            $table->string('name', 120)->comment('Nombre del país');
            $table->timestamps();
            $table->index('code');
        });

        // Tabla de departamentos/estados geográficos
        Schema::create('departments_geo', function (Blueprint $table) {
            $table->id()->comment('PK. Identificador del departamento geográfico');
            $table->foreignId('country_id')->constrained('countries')->comment('FK a countries.id');
            $table->string('code', 10)->comment('Código del departamento (ej: 05=Antioquia, 11=Bogotá)');
            $table->string('name', 120)->comment('Nombre del departamento');
            $table->string('iso_code', 5)->nullable()->comment('Código ISO subdivisions (CO-ANT, CO-DC, etc.)');
            $table->string('dane_code', 5)->nullable()->comment('Código DANE completo del departamento');
            $table->string('capital_city', 120)->nullable()->comment('Ciudad capital del departamento');
            $table->timestamps();
            
            $table->unique(['country_id', 'code'], 'uq_deptsgeo_country_code');
            $table->unique('dane_code', 'uq_deptsgeo_dane_code');
            $table->index('country_id', 'idx_deptsgeo_country');
        });

        // Tabla de municipios
        Schema::create('municipalities', function (Blueprint $table) {
            $table->id()->comment('PK. Identificador del municipio');
            $table->foreignId('department_geo_id')->constrained('departments_geo')->comment('FK a departments_geo.id');
            $table->string('dane_code', 10)->unique()->comment('Código DANE del municipio (5 dígitos: 05001=Medellín)');
            $table->string('name', 120)->comment('Nombre del municipio');
            $table->enum('municipality_type', ['municipio', 'distrito', 'área_no_municipalizada'])
                  ->default('municipio')
                  ->comment('Tipo según clasificación DANE');
            $table->enum('population_category', ['especial', 'primera', 'segunda', 'tercera', 'cuarta', 'quinta', 'sexta'])
                  ->nullable()
                  ->comment('Categoría poblacional DANE');
            $table->boolean('is_capital')->default(false)->comment('Indica si es capital departamental');
            $table->integer('altitude_meters')->nullable()->comment('Altitud sobre el nivel del mar en metros');
            $table->timestamps();
            
            $table->index('department_geo_id', 'idx_municipalities_dept');
            $table->index('is_capital', 'idx_municipalities_capital');
        });

        // Tabla de tipos de documento
        Schema::create('document_types', function (Blueprint $table) {
            $table->id()->comment('PK. Tipo de documento');
            $table->foreignId('country_id')->nullable()->constrained('countries')->comment('FK a countries.id - país donde aplica el documento');
            $table->string('code', 20)->comment('Código del tipo (CC, CE, PP, NIT, etc.)');
            $table->string('name', 120)->comment('Nombre del tipo de documento');
            $table->string('validation_pattern', 255)->nullable()->comment('Patrón regex para validación');
            $table->integer('max_length')->nullable()->comment('Longitud máxima permitida');
            $table->boolean('is_active')->default(true)->comment('Estado activo del tipo');
            $table->timestamps();
            
            $table->unique(['country_id', 'code'], 'uq_document_types_country_code');
            $table->index('country_id', 'idx_document_types_country');
        });

        // Tabla de tipos de contacto
        Schema::create('contact_types', function (Blueprint $table) {
            $table->id()->comment('PK. Tipo de contacto');
            $table->string('code', 50)->unique()->comment('Código del tipo (EMAIL, PHONE, MOBILE, FAX, WEBSITE)');
            $table->string('name', 120)->comment('Nombre del tipo de contacto');
            $table->string('validation_pattern', 255)->nullable()->comment('Patrón regex para validación (opcional)');
            $table->boolean('is_active')->default(true)->comment('Estado activo del tipo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('contact_types');
        Schema::dropIfExists('document_types');
        Schema::dropIfExists('municipalities');
        Schema::dropIfExists('departments_geo');
        Schema::dropIfExists('countries');
        Schema::enableForeignKeyConstraints();
    }
};
