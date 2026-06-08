<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inspection_work_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->foreignId('work_request_id')->constrained('work_requests')->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('inspection_sections')->nullOnDelete();
            $table->timestamps();

            $table->unique('work_request_id'); // una SR pertenece a una sola inspección
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_work_requests');
    }
};
