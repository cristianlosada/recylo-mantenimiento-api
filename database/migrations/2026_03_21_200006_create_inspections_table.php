<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies');
            $table->foreignId('template_id')->constrained('inspection_templates');
            $table->foreignId('asset_id')->constrained('assets');
            $table->foreignId('operator_id')->constrained('users');
            $table->foreignId('shift_id')->nullable()->constrained('inspection_shifts')->nullOnDelete();
            $table->date('inspection_date');
            $table->enum('status', ['draft', 'completed'])->default('draft');
            $table->boolean('has_findings')->default(false);
            $table->foreignId('work_request_id')->nullable()->constrained('work_requests')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('inspections'); }
};
