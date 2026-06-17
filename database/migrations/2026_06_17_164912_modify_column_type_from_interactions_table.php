<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('interactions', function (Blueprint $table) {
            $table->renameColumn('type', 'tempcolumn');
        });
        Schema::table('interactions', function (Blueprint $table) {
            $table->string('interaction_type', 255)->after('post_id');
        });
        DB::statement('UPDATE interactions SET interaction_type = tempcolumn');
        // Step 3: Drop old column
        Schema::table('interactions', function (Blueprint $table) {
            $table->dropColumn('tempcolumn');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
