<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('inspection_response_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_id')->constrained('inspection_responses')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name', 255);
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime_type', 100)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('inspection_response_photos'); }
};
