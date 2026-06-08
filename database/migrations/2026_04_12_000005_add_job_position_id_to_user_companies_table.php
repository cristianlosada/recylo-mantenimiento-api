<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_companies', function (Blueprint $table) {
            $table->foreignId('job_position_id')
                ->nullable()
                ->after('job_position')
                ->constrained('job_positions')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('user_companies', function (Blueprint $table) {
            $table->dropForeign(['job_position_id']);
            $table->dropColumn('job_position_id');
        });
    }
};
