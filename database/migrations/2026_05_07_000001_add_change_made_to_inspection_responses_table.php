<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspection_responses', function (Blueprint $table) {
            $table->boolean('change_made')->nullable()->after('observation');
        });
    }

    public function down(): void
    {
        Schema::table('inspection_responses', function (Blueprint $table) {
            $table->dropColumn('change_made');
        });
    }
};
