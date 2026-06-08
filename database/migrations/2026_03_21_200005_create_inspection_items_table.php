<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('inspection_sections')->cascadeOnDelete();
            $table->string('name', 200);
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('non_conformant_value', 100)->nullable(); // value that triggers finding
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('inspection_items'); }
};
