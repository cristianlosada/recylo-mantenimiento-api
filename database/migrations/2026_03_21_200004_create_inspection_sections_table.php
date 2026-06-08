<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('inspection_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('inspection_templates')->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->json('response_options'); // e.g. ["BAJO","ALTO","NORMAL"]
            $table->boolean('has_observation')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('inspection_sections'); }
};
