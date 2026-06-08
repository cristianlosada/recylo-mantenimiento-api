<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agregar campos para soportar solicitudes públicas desde QR Code
     */
    public function up(): void
    {
        Schema::table('work_requests', function (Blueprint $table) {
            // Hacer requester_id nullable para solicitudes públicas
            $table->foreignId('requester_id')->nullable()->change();
            
            // Campos para solicitudes públicas (sin usuario autenticado)
            $table->string('requester_name', 255)->nullable()->after('requester_contact')->comment('Nombre del solicitante (público)');
            $table->string('requester_email', 255)->nullable()->after('requester_name')->comment('Email del solicitante (público)');
            $table->string('requester_phone', 20)->nullable()->after('requester_email')->comment('Teléfono del solicitante (público)');
            $table->boolean('is_public_request')->default(false)->after('requester_phone')->comment('¿Solicitud creada desde formulario público?');
            
            // Índice para búsqueda por email
            $table->index('requester_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_requests', function (Blueprint $table) {
            // Restaurar requester_id como requerido
            $table->foreignId('requester_id')->nullable(false)->change();
            
            // Eliminar campos públicos
            $table->dropIndex(['requester_email']);
            $table->dropColumn([
                'requester_name',
                'requester_email',
                'requester_phone',
                'is_public_request',
            ]);
        });
    }
};
