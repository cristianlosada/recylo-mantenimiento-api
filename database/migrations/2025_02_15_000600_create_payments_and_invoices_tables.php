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
        // Tabla de métodos de pago por empresa
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->enum('gateway', ['stripe', 'paypal', 'mercado_pago', 'bank_transfer'])->default('stripe');
            $table->string('method_name'); // Ej: "Tarjeta de crédito", "PayPal", etc
            $table->string('token')->nullable(); // Token del gateway
            $table->string('last_four')->nullable(); // Últimos 4 dígitos
            $table->enum('card_brand', ['visa', 'mastercard', 'amex', 'discover', 'other'])->nullable();
            $table->string('customer_id')->nullable(); // ID del cliente en el gateway
            $table->boolean('is_primary')->default(false);
            $table->date('expiry_date')->nullable();
            $table->json('metadata')->nullable(); // Datos adicionales del gateway
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['company_id', 'gateway']);
            $table->index(['company_id', 'is_primary']);
        });

        // Tabla de facturas/invoices
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('company_plan_subscription_id')->nullable()->constrained('company_plan_subscriptions')->onDelete('set null');
            $table->string('invoice_number')->unique(); // Ej: INV-2025-000001
            $table->date('invoice_date');
            $table->date('due_date');
            $table->enum('status', ['draft', 'sent', 'viewed', 'partially_paid', 'paid', 'overdue', 'cancelled', 'refunded'])->default('draft');
            $table->enum('type', ['subscription', 'manual', 'one_time', 'refund'])->default('subscription');
            $table->decimal('subtotal', 12, 2); // Antes de impuestos
            $table->decimal('tax_amount', 12, 2)->default(0); // Monto de impuestos
            $table->decimal('discount_amount', 12, 2)->default(0); // Descuentos aplicados
            $table->decimal('total_amount', 12, 2); // Total a pagar
            $table->decimal('paid_amount', 12, 2)->default(0); // Cantidad ya pagada
            $table->text('description')->nullable(); // Descripción de conceptos
            $table->text('notes')->nullable(); // Notas adicionales
            $table->string('currency', 3)->default('USD'); // Código de moneda
            $table->json('line_items')->nullable(); // Items de la factura en JSON
            $table->json('payment_terms')->nullable(); // Términos de pago personalizados
            $table->string('external_invoice_id')->nullable(); // ID en sistema externo
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'invoice_date']);
            $table->index(['due_date', 'status']); // Para reportes de vencimiento
            $table->index(['invoice_number']);
        });

        // Tabla de pagos
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->onDelete('set null');
            $table->string('payment_reference')->unique(); // Referencia única del pago (PAY-2025-000001)
            $table->decimal('amount', 12, 2); // Monto pagado
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded'])->default('pending');
            $table->string('gateway_name')->nullable(); // Nombre del gateway utilizado
            $table->string('gateway_transaction_id')->nullable(); // ID de transacción del gateway
            $table->string('gateway_reference')->nullable(); // Referencia del gateway
            $table->timestamp('paid_at')->nullable();
            $table->text('error_message')->nullable(); // Mensaje de error si falló
            $table->json('gateway_response')->nullable(); // Respuesta completa del gateway
            $table->json('metadata')->nullable(); // Datos adicionales
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['company_id', 'status']);
            $table->index(['invoice_id', 'status']);
            $table->index(['payment_reference']);
            $table->index(['gateway_transaction_id']);
            $table->index(['paid_at']); // Para reportes de ingresos
        });

        // Tabla de reembolsos (refunds)
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('payment_id')->constrained('payments')->onDelete('cascade');
            $table->string('refund_reference')->unique(); // REF-2025-000001
            $table->decimal('amount', 12, 2); // Monto reembolsado
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->enum('reason', ['customer_request', 'payment_error', 'duplicate_charge', 'subscription_cancellation', 'other'])->default('customer_request');
            $table->text('notes')->nullable();
            $table->string('gateway_refund_id')->nullable(); // ID del reembolso en el gateway
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['company_id', 'status']);
            $table->index(['payment_id']);
        });

        // Tabla de historial de cambios de suscripción
        Schema::create('subscription_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('company_plan_subscription_id')->constrained('company_plan_subscriptions')->onDelete('cascade');
            $table->enum('change_type', ['upgrade', 'downgrade', 'renewal', 'reactivation', 'cancellation'])->default('renewal');
            $table->foreignId('from_plan_id')->nullable()->constrained('plans')->onDelete('set null');
            $table->foreignId('to_plan_id')->constrained('plans')->onDelete('restrict');
            $table->decimal('prorated_amount', 12, 2)->nullable(); // Monto prorrateado
            $table->date('effective_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['company_id', 'change_type']);
            $table->index(['effective_date']);
        });

        // Tabla de facturas de crédito (credit notes)
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->string('credit_note_number')->unique(); // CN-2025-000001
            $table->date('issue_date');
            $table->enum('status', ['draft', 'sent', 'applied', 'refunded'])->default('draft');
            $table->decimal('amount', 12, 2); // Monto del crédito
            $table->enum('reason', ['return', 'discount', 'error', 'adjustment', 'other'])->default('adjustment');
            $table->text('description')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['company_id', 'status']);
            $table->index(['invoice_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('subscription_changes');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payment_methods');
        Schema::enableForeignKeyConstraints();
    }
};
