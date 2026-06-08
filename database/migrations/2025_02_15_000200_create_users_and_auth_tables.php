<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas de usuarios y autenticación: consolidación completa
     * Incluye users, user_documents, user_contacts con todos los campos en una sola migración
     * Reemplaza: 0001_01_01_000000_create_users_table + 2025_10_12_210010_enhance_users_table
     */
    public function up(): void
    {
        // Tabla de usuarios unificada
        Schema::create('users', function (Blueprint $table) {
            // Identificador y datos básicos
            $table->id()->comment('PK. Identificador del usuario');
            
            // Información de identidad normalizados
            $table->foreignId('document_type_id')->nullable()->constrained('document_types')->comment('FK a document_types.id');
            $table->string('document_number', 50)->nullable()->comment('Número de documento de identidad');
            $table->string('first_name', 120)->nullable()->comment('Primer nombre');
            $table->string('middle_name', 120)->nullable()->comment('Segundo nombre (opcional)');
            $table->string('last_name', 120)->nullable()->comment('Primer apellido');
            $table->string('second_last_name', 120)->nullable()->comment('Segundo apellido (opcional)');
            
            // Contacto principal
            $table->string('email', 255)->unique()->comment('Email (usado para autenticación)');
            $table->timestamp('email_verified_at')->nullable()->comment('Fecha de verificación del email');
            
            // Datos personales
            $table->date('birth_date')->nullable()->comment('Fecha de nacimiento');
            $table->enum('gender', ['male', 'female', 'other', 'prefer_not_to_say'])->nullable()->comment('Género');
            $table->foreignId('nationality_country_id')->nullable()->constrained('countries')->comment('FK a countries.id - nacionalidad');
            
            // Seguridad y autenticación
            $table->string('password')->comment('Contraseña hasheada');
            $table->rememberToken()->comment('Token para "recuérdame"');
            $table->boolean('mfa_enabled')->default(false)->comment('Indica si tiene MFA habilitado');
            $table->string('mfa_secret', 255)->nullable()->comment('Secreto para autenticación de dos factores');
            
            // Auditoría de sesiones
            $table->timestamp('last_login_at')->nullable()->comment('Último acceso');
            $table->timestamp('password_changed_at')->nullable()->comment('Última vez que cambió la contraseña');
            $table->integer('failed_login_attempts')->default(0)->comment('Intentos fallidos de inicio de sesión');
            $table->timestamp('locked_until')->nullable()->comment('Bloqueado hasta esta fecha/hora');
            
            // Estado del usuario
            $table->enum('status', ['active', 'suspended', 'pending_verification'])->default('pending_verification')->comment('Estado del usuario');
            
            // Auditoría general
            $table->timestamps();
            $table->softDeletes()->comment('Borrado lógico');
            
            // Índices de búsqueda frecuente
            $table->unique(['document_type_id', 'document_number'], 'uq_users_document');
            $table->index('document_type_id', 'idx_users_document_type');
            $table->index('nationality_country_id', 'idx_users_nationality');
            $table->index(['status', 'email'], 'idx_users_status_email');
        });

        // Tabla de tokens de restablecimiento de contraseña (Laravel estándar)
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Tabla de sesiones de usuario (Laravel estándar)
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade')->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Tabla de sesiones personalizadas del sistema
        // Columnas alineadas con: App\Models\UserSession + App\Http\Controllers\Api\AuthController
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id()->comment('PK. Sesión de usuario');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('FK a users.id');
            $table->string('session_id', 255)->nullable()->comment('Referencia a sessions.id de Laravel (opcional)');
            $table->string('ip_address', 45)->nullable()->comment('Dirección IP del cliente');
            $table->text('user_agent')->nullable()->comment('Información del navegador/cliente');
            $table->text('payload')->nullable()->comment('Datos de sesión JSON (token_name, login_method, remember_me)');
            $table->json('device_info')->nullable()->comment('Información del dispositivo (JSON)');
            $table->json('location')->nullable()->comment('Ubicación geográfica (JSON: city, country)');
            $table->timestamp('login_time')->nullable()->comment('Fecha/hora de inicio de sesión');
            $table->timestamp('last_activity')->nullable()->comment('Última actividad en la sesión');
            $table->timestamp('logout_time')->nullable()->comment('Fecha/hora de cierre de sesión');
            $table->boolean('is_active')->default(true)->comment('Indica si la sesión está activa');
            $table->enum('session_type', ['web', 'api', 'mobile'])->default('api')->comment('Tipo de sesión');
            $table->timestamp('expires_at')->nullable()->comment('Fecha de expiración de la sesión');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('user_id', 'idx_user_sessions_user');
            $table->index('is_active', 'idx_user_sessions_active');
            $table->index('expires_at', 'idx_user_sessions_expires');
            $table->index(['user_id', 'is_active'], 'idx_user_sessions_user_active');
        });

        // Tabla de documentos del usuario
        Schema::create('user_documents', function (Blueprint $table) {
            $table->id()->comment('PK. Documento del usuario');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('FK a users.id');
            $table->foreignId('document_type_id')->constrained('document_types')->comment('FK a document_types.id');
            $table->string('document_number', 50)->comment('Número o código del documento');
            $table->string('file_path', 255)->nullable()->comment('Ruta del archivo almacenado');
            $table->date('issued_at')->nullable()->comment('Fecha de emisión');
            $table->date('expires_at')->nullable()->comment('Fecha de vencimiento');
            $table->text('notes')->nullable()->comment('Notas adicionales');
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['user_id', 'document_type_id', 'document_number'], 'uq_user_doc');
            $table->index('user_id', 'idx_user_docs_user');
            $table->index('document_type_id', 'idx_user_docs_type');
        });

        // Tabla de contactos del usuario
        Schema::create('user_contacts', function (Blueprint $table) {
            $table->id()->comment('PK. Contacto del usuario');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('FK a users.id');
            $table->foreignId('contact_type_id')->constrained('contact_types')->comment('FK a contact_types.id');
            $table->string('value', 190)->comment('Valor del contacto (email, teléfono, etc.)');
            $table->boolean('is_primary')->default(false)->comment('Indica si es contacto principal');
            $table->boolean('is_verified')->default(false)->comment('Indica si ha sido verificado');
            $table->timestamp('verified_at')->nullable()->comment('Fecha de verificación');
            $table->timestamps();
            
            $table->unique(['user_id', 'contact_type_id', 'value'], 'uq_user_contact_unique');
            $table->index('user_id', 'idx_user_contacts_user');
            $table->index('contact_type_id', 'idx_user_contacts_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('user_contacts');
        Schema::dropIfExists('user_documents');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::enableForeignKeyConstraints();
    }
};
