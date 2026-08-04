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
        // stores values up to 99,999,999.9999
        Schema::table('interests', function (Blueprint $table) {
            $table->decimal('weight', 12, 4)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('interests', function (Blueprint $table) {
            $table->decimal('weight', 12, 4)->default(0)->change();
        });
    }
};
