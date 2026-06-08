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
        // TABLA: notification_logs (Log centralizado de notificaciones automáticas)
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            
            // Información de la notificación
            $table->string('notification_type', 50)->comment('Tipo: work_order, work_request'); // work_order, work_request
            $table->string('event_type', 50)->comment('Evento: create, open, close, approve, reject'); // create, open, close, approve, reject, etc
            $table->enum('channel', ['email', 'push', 'sms', 'in_app'])->default('email')->comment('Canal de entrega');
            $table->enum('status', ['pending', 'sent', 'failed', 'bounced'])->default('pending')->comment('Estado del envío');
            
            // Relacionados a la orden o solicitud
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->onDelete('cascade')->comment('Orden de trabajo relacionada');
            $table->foreignId('work_request_id')->nullable()->constrained('work_requests')->onDelete('cascade')->comment('Solicitud relacionada');
            $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade')->comment('Activo');
            
            // Destinatarios
            $table->string('recipient_email', 255)->comment('Email destinatario');
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario destinatario (opcional)');
            
            // Contenido
            $table->string('subject', 255)->comment('Asunto del email/notificación');
            $table->longText('message')->comment('Cuerpo del mensaje');
            
            // Seguimiento
            $table->timestamp('scheduled_at')->nullable()->comment('Fecha programada para envío');
            $table->timestamp('sent_at')->nullable()->comment('Fecha de envío real');
            $table->timestamp('delivered_at')->nullable()->comment('Fecha de entrega confirmada');
            $table->timestamp('opened_at')->nullable()->comment('Fecha de apertura (para email tracking)');
            $table->text('error_message')->nullable()->comment('Mensaje de error si falló');
            $table->integer('retry_count')->default(0)->comment('Número de reintentos');
            
            // Metadata
            $table->json('metadata')->nullable()->comment('Datos adicionales en JSON');
            
            $table->timestamps();
            $table->softDeletes();

            // ÍNDICES
            $table->index('notification_type');
            $table->index('event_type');
            $table->index('status');
            $table->index('channel');
            $table->index('asset_id');
            $table->index('work_order_id');
            $table->index('work_request_id');
            $table->index('recipient_email');
            $table->index('recipient_user_id');
            $table->index('sent_at');
            $table->index('created_at');
            
            // ÍNDICE COMPUESTO para búsquedas frecuentes
            $table->index(['notification_type', 'status', 'asset_id']);
            $table->index(['work_order_id', 'status', 'created_at']);
            $table->index(['work_request_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
