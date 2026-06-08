<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('inspection_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inspection_items');
            $table->string('response_value', 100)->nullable();
            $table->boolean('is_non_conformant')->default(false);
            $table->text('observation')->nullable();
            $table->timestamps();
            $table->unique(['inspection_id', 'item_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('inspection_responses'); }
};
